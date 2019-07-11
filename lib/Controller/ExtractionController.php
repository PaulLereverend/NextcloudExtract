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

	public function extractHere($nameOfFile, $directory, $external, $shareOwner = null) {
		if (!extension_loaded ("zip")){
			$response = array_merge($response, array("code" => 0, "desc" => $l->t("extract", "Zip extension is not available")));
			return json_encode($response);
		}
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
			$response = array_merge($response, array("code" => 0, "desc" => $l->t("extract", "Can't find zip file")));
		}else{
			if ($shareOwner != null){
				$this->UserId = $shareOwner;
			}
			if ($zip->open($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$nameOfFile) === TRUE) {
				for($i = 0; $i < $zip->numFiles; $i++) {
					$zip->extractTo($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory, array($zip->getNameIndex($i)));
					$scan = self::scanFolder('/'.$this->UserId.'/files'.$directory.'/'.$zip->getNameIndex($i));
					if(!$scan){
						return $scan;
					}
				}
				$zip->close();
				$response = array_merge($response, array("code" => 1));
			}else{
				$response = array_merge($response, array("code" => 0, "desc" => $l->t("extract", "Can't open zip file at ").$this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$nameOfFile));
			}
		}
		return json_encode($response);
	}
	/**
	* @NoAdminRequired
	*/
	public function extractHereRar($nameOfFile, $directory, $external, $shareOwner = null) {
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
								$response = array_merge($response, array("code" => 0, "desc" => $l->t("extract", "rar extension or unrar is not installed or available")));
								return json_encode($response);
							}else{
								$response = array_merge($response, array("code" => 1));
								return json_encode($response);
							}
					}
					return;
				}
			}
			$response = array_merge($response, array("code" => 0, "desc" => $l->t("extract", "Can't find rar file")));
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
					$scan = self::scanFolder('/'.$this->UserId.'/files'.$directory.'/'.$fileOpen->getName());
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
						$response = array_merge($response, array("code" => 0, "desc" => $l->t("extract", "rar extension or unrar is not installed or available")));
						return json_encode($response);
					}else{
						$response = array_merge($response, array("code" => 0, "desc" => $l->t("extract", "Can't find rar file at ").$file));
						return json_encode($response);
					}
				}else{
					foreach ($output as $val) {
						if(preg_split('/ /', $val, -1, PREG_SPLIT_NO_EMPTY)[0] == "Extracting" &&
						preg_split('/ /', $val, -1, PREG_SPLIT_NO_EMPTY)[1] != "from"){
							$fichier = substr(strrchr($PATH, "/"), 1);
							$scan = self::scanFolder('/'.$this->UserId.'/files'.$directory.'/'.$fichier);
							if(!$scan){
								return $scan;
							}
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
						$response = array_merge($response, array("code" => 0, "desc" => $l->t("extract", "p7zip and p7zip-full are not installed or available")));
						return json_encode($response);
					}else{
						$response = array_merge($response, array("code" => 1));
						return json_encode($response);
					}
				}
			}
			$response = array_merge($response, array("code" => 0, "desc" => $l->t("extract", "Can't find archive on external local storage")));
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
					$response = array_merge($response, array("code" => 0, "desc" => $l->t("extract", "p7zip and p7zip-full are not installed or available")));
					return json_encode($response);
				}else{
					$response = array_merge($response, array("code" => 0, "desc" => $l->t("extract", "Can't find archive at ").$file));
					return json_encode($response);
				}
			}
			$scan = self::scanFolder($scanpath);
			if($scan != 1){
				return $scan;
			}
			$response = array_merge($response, array("code" => 1));
			return json_encode($response);
		}
	}
	public function scanFolder($path)
    {
		$response = array();
        $user = \OC::$server->getUserSession()->getUser()->getUID();
		$scanner = new \OC\Files\Utils\Scanner($user, \OC::$server->getDatabaseConnection(), \OC::$server->getLogger());

		try {
            $scanner->scan($path, $recusive = false);
        } catch (ForbiddenException $e) {
			$response = array_merge($response, array("code" => 0, "desc" => $e));
			return json_encode($response);
        }catch (NotFoundException $e){
			$response = array_merge($response, array("code" => 0, "desc" => 
$l->t("extract", "Can't scan file at ").$path));
			return json_encode($response);
		}catch (\Exception $e){
			$response = array_merge($response, array("code" => 0, "desc" => $e));
			return json_encode($response);
		}
		return 1;
	}
	public function getExternalMP(){
		$mounts = \OC_Mount_Config::getAbsoluteMountPoints($this->UserId);
		$externalMountPoints = array();
		foreach($mounts as $mount){
			if ($mount["backend"] == "Local"){
				$externalMountPoints[] = $mount["options"]["datadir"];
			}
		}
		return $externalMountPoints;
	}
}
