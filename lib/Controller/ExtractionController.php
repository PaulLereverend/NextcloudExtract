<?php
namespace OCA\Extract\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\Files\NotFoundException;
use ZipArchive;
use Rar;
use PharData;
use \OCP\IConfig;
use OCP\IL10N;
use OCP\EventDispatcher\IEventDispatcher;
use OC\Files\Filesystem;
use \OC\Files\Utils\Scanner;

class ExtractionController extends Controller {
	private $UserId;
	private $config;
	private $l;
	public function __construct(IConfig $config,$AppName, IRequest $request, string $UserId, IL10N $l){
		parent::__construct($AppName, $request);
		$this->config = $config;
		$this->UserId = $UserId;
		$this->l = $l;
		//header("Content-type: application/json");
	}


	public function getFile($directory, $fileName){
		return Filesystem::getLocalFile($directory . '/' . $fileName);
	}


	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 *
	 * @NoCSRFRequired
	 */
    /**
	* @NoAdminRequired
	*/

	public function extract($nameOfFile, $directory, $external, $type){
		
		switch ($type) {
			case 'zip':
				return $this->extractZip($nameOfFile, $directory, $external);
				break;
			case 'rar':
				break;
			default:
				break;
		}
	}
	public function extractZip($nameOfFile, $directory, $external){
		if (!extension_loaded("zip")){
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Zip extension is not available")));
			return json_encode($response);
		}

		$file = $this->getFile($directory, $nameOfFile);
		$dir = dirname($file);
		$extractTo = $dir . '/' . pathinfo($nameOfFile)['filename'];

		$zip = new ZipArchive();
		$response = array();

		if (!$zip->open($file) === TRUE){
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Can't open zip file ")));
			return json_encode($response);
		}

		// if the file is un external storage
		if($external){
			$extractTo = Filesystem::getLocalFolder('/') . '/' . pathinfo($nameOfFile)['filename'] . '_extract_tmp';
			$tmpPath = '/' . pathinfo($nameOfFile)['filename'] . '_extract_tmp';
			$zip->extractTo($extractTo);
			Filesystem::mkdir($tmpPath);
			Filesystem::rename($tmpPath, $directory . '/' . pathinfo($nameOfFile)['filename']);
		}else{
			$zip->extractTo($extractTo);
			Filesystem::mkdir($directory . '/' . pathinfo($file)['filename']);
		}
		$zip->close();
		$response = array_merge($response, array("code" => 1));
		return json_encode($response);
	}
	public function extractHere($nameOfFile, $directory, $external, $shareOwner = null) {
		/*if (preg_match('/(\/|^)\.\.(\/|$)/', $nameOfFile)) {
			$response = ['code' => 0, 'desc' => 'Can\'t find zip file'];
			return json_encode($response);
		 }
		 if (preg_match('/(\/|^)\.\.(\/|$)/', $directory)) {
			$response = ['code' => 0, 'desc' => 'Can\'t open zip file at directory'];
			return json_encode($response);
		 }*/
		if (!extension_loaded("zip")){
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Zip extension is not available")));
			return json_encode($response);
		}
		$file = $this->getFile($directory, $nameOfFile);
		$dir = dirname($file);

		$zip = new ZipArchive();
		$response = array();
		if ($external){
			$externalMountPoints = $this->getExternalMP();
			foreach($externalMountPoints as $externalMP){
				if ($zip->open($externalMP.$directory.'/'.$nameOfFile) === TRUE) {
					$zip->extractTo($externalMP.$directory.'/');
					$zip->close();
					$response = array_merge($response, array("code" => 1));
					return json_encode($response);
				}
			}
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Can't find zip file")));
		}else{
			if ($shareOwner != null){
				$this->UserId = $shareOwner;
			}
			if ($zip->open($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$nameOfFile) === TRUE) {
				for($i = 0; $i < $zip->numFiles; $i++) {
					$zip->extractTo($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory, array($zip->getNameIndex($i)));
					$scan = self::scanFolder('/'.$this->UserId.'/files'.$directory.'/'.$zip->getNameIndex($i), $this->UserId);
					if(!$scan){
						return $scan;
					}
				}
				$zip->close();
				$response = array_merge($response, array("code" => 1));
			}else{
				$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Can't open zip file at ").$this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$nameOfFile));
			}
		}
		return json_encode($response);
	}
	/**
	* @NoAdminRequired
	*/
	public function extractHereRar($nameOfFile, $directory, $external, $shareOwner = null) {
		if (preg_match('/(\/|^)\.\.(\/|$)/', $nameOfFile)) {
			$response = ['code' => 0, 'desc' => 'Can\'t find zip file'];
			return json_encode($response);
		 }
		 if (preg_match('/(\/|^)\.\.(\/|$)/', $directory)) {
			$response = ['code' => 0, 'desc' => 'Can\'t open zip file at directory'];
			return json_encode($response);
		 }
		$response = array();
		if ($external){
			$externalMountPoints = $this->getExternalMP();
			foreach($externalMountPoints as $externalMP){
				if (file_exists($externalMP.$directory."/".$nameOfFile)){
					if (extension_loaded ("rar")){
						$rar_file = rar_open($externalMP.$directory.'/'.$nameOfFile);
						$list = rar_list($rar_file);
						foreach($list as $file) {
							$entry = rar_entry_get($rar_file, $file->getName());
							$entry->extract($externalMP.$directory.'/');
						}
						rar_close($rar_file);
						$response = array_merge($response, array("code" => 1));
						return json_encode($response);
					}else{
							exec('unrar x ' .escapeshellarg($externalMP.$directory. '/' .$nameOfFile). ' -R ' .escapeshellarg($externalMP.$directory). ' -o+',$output,$return);
							if (sizeof($output) == 0){
								$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("the rar extension is not available or unrar is not installed\n
								DEBUG(".$return.")".$output)));
								return json_encode($response);
							}else{
								$response = array_merge($response, array("code" => 1));
								return json_encode($response);
							}
					}
					return;
				}
			}
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Can't find rar file")));
			return json_encode($response);
		}else{
			if ($shareOwner != null){
				$this->UserId = $shareOwner;
			}
			$file = $this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$nameOfFile;
			$dir = $this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory;
			if (extension_loaded ("rar")){
				$rar_file = rar_open($file);
				$list = rar_list($rar_file);
				foreach($list as $fileOpen) {
					$entry = rar_entry_get($rar_file, $fileOpen->getName());
					$entry->extract($dir); // extract to the current dir
					$scan = self::scanFolder('/'.$this->UserId.'/files'.$directory.'/'.$fileOpen->getName(), $this->UserId);
					if(!$scan){
						return $scan;
					}
				}
				rar_close($rar_file);
				$response = array_merge($response, array("code" => 1));
				return json_encode($response);

			}else{
				exec('unrar x ' .escapeshellarg($file). ' -R ' .escapeshellarg($dir). ' -o+',$output,$return);
				if(sizeof($output) <= 4){
					if (file_exists($file)){
						$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("the rar extension is not available or unrar is not installed\n
						DEBUG(".$return.")".$output)));
						return json_encode($response);
					}else{
						$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Can't find rar file at ").$file));
						return json_encode($response);
					}
				}else{
					foreach ($output as $val) {
						$scan = self::scanFolder('/'.$this->UserId.'/files'.$directory.'/', $this->UserId);
						if(!$scan){
							return $scan;
						}
					}
					$response = array_merge($response, array("code" => 1));
					return json_encode($response);
				}
			}
		}
	}
	/**
	* @NoAdminRequired
	*/
	public function extractHereOthers($nameOfFile, $directory, $external, $shareOwner = null) {
		if (preg_match('/(\/|^)\.\.(\/|$)/', $nameOfFile)) {
			$response = ['code' => 0, 'desc' => 'Can\'t find zip file'];
			return json_encode($response);
		 }
		 if (preg_match('/(\/|^)\.\.(\/|$)/', $directory)) {
			$response = ['code' => 0, 'desc' => 'Can\'t open zip file at directory'];
			return json_encode($response);
		 }
		$response = array();
		if ($external){
			$externalMountPoints = $this->getExternalMP();
			foreach($externalMountPoints as $externalMP){
				if (file_exists($externalMP.$directory."/".$nameOfFile)){
					if (pathinfo(pathinfo(escapeshellarg($nameOfFile))["filename"])["extension"] == "tar"){
						exec('7za -y x ' .escapeshellarg($externalMP.$directory. '/' .$nameOfFile). ' -o' .escapeshellarg($externalMP.$directory. '/')
					.'&& 7za -y x ' .escapeshellarg($externalMP.$directory. '/' .pathinfo($nameOfFile)["filename"]). ' -o' .escapeshellarg($externalMP.$directory. '/' .pathinfo(pathinfo($nameOfFile)["filename"])['filename']. '/')
					, $output,$return);
					unlink($externalMP.$directory. '/' .pathinfo($nameOfFile)["filename"]);
					}else{
						exec('7za -y x ' .escapeshellarg($externalMP.$directory. '/' .$nameOfFile). ' -o' .escapeshellarg($externalMP.$directory. '/' .pathinfo($nameOfFile)['filename']. '/'), $output,$return);
					}
					if(sizeof($output) <= 5){
						$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("the p7zip extension is not available or p7zip-full is not installed\n
						DEBUG(".$return.")".$output)));
						return json_encode($response);
					}else{
						$response = array_merge($response, array("code" => 1));
						return json_encode($response);
					}
				}
			}
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Can't find archive on external local storage")));
			return json_encode($response);
		}else{
			if ($shareOwner != null){
				$this->UserId = $shareOwner;
			}
			$file = $this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$nameOfFile;
			$dir = $this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.pathinfo($nameOfFile)['filename'];
			$scanpath = '/'.$this->UserId.'/files'.$directory.'/'.pathinfo($nameOfFile)['filename'];
			if (pathinfo(pathinfo(escapeshellarg($nameOfFile))["filename"])["extension"] == "tar"){
				$dir = $this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/';
				$filetar = $this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.pathinfo($nameOfFile)['filename'];
				$dirtar = $this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.pathinfo(pathinfo($nameOfFile)['filename'])['filename'];
				$scanpath = '/'.$this->UserId.'/files'.$directory.'/'.pathinfo(pathinfo($nameOfFile)['filename'])['filename'];
				exec('7za -y x ' .escapeshellarg($file). ' -o' .escapeshellarg($dir).'&& 7za -y x ' .escapeshellarg($filetar). ' -o' .escapeshellarg($dirtar), $output,$return);
				unlink($dir);
			}else{
				exec('7za -y x ' .escapeshellarg($file). ' -o' .escapeshellarg($dir),$output,$return);
			}
			if(sizeof($output) <= 5){
				if (file_exists($file)){
					$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("the p7zip extension is not available or p7zip-full is not installed\n
					DEBUG(".$return.")".$output)));
					return json_encode($response);
				}else{
					$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Can't find archive at ").$file));
					return json_encode($response);
				}
			}
			$scan = self::scanFolder($scanpath, $this->UserId);
			if($scan != 1){
				return $scan;
			}
			$response = array_merge($response, array("code" => 1));
			return json_encode($response);
		}
	}
	public function scanFolder($path, $user)
    {
		$response = array();
		/*if($user == null){
			$user = \OC::$server->getUserSession()->getUser()->getUID();
		}*/
		$version = \OC::$server->getConfig()->getSystemValue('version');
		 if((int)substr($version, 0, 2) < 18){
			$scanner = new Scanner($user, \OC::$server->getDatabaseConnection(), \OC::$server->getLogger());
		 }else{
			$scanner = new Scanner($user, \OC::$server->getDatabaseConnection(),\OC::$server->query(IEventDispatcher::class), \OC::$server->getLogger());
		 }
		try {
            error_log($scanner->scan($path, $recusive = true));
        } catch (ForbiddenException $e) {
			$response = array_merge($response, array("code" => 0, "desc" => $e));
			return json_encode($response);
        }catch (NotFoundException $e){
			error_log($e);
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Can't scan file at "). ' ...' . $path));
			return json_encode($response);
		}catch (\Exception $e){
			$response = array_merge($response, array("code" => 0, "desc" => $e));
			return json_encode($response);
		}
		return 1;
	}
	public function getExternalMP(){
		$version = \OC::$server->getConfig()->getSystemValue('version');
		if((int)substr($version, 0, 2) >= 20){
			$mounts = \OCA\Files_External\MountConfig::getAbsoluteMountPoints($this->UserId);
		}else{
			$mounts = \OC_Mount_Config::getAbsoluteMountPoints($this->UserId);
		}

		$externalMountPoints = array();
		foreach($mounts as $mount){
			if ($mount["backend"] == "Local"){
				$externalMountPoints[] = $mount["options"]["datadir"];
			}
		}
		return $externalMountPoints;
	}
	public function delete_files($target) {
		if(is_dir($target)){
			$files = glob( $target . '*', GLOB_MARK ); //GLOB_MARK adds a slash to directories returned
	
			foreach( $files as $file ){
				delete_files( $file );      
			}
	
			rmdir( $target );
		} elseif(is_file($target)) {
			unlink( $target );  
		}
	}
}
