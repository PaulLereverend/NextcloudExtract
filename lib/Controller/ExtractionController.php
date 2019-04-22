<?php
namespace OCA\Extract\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use ZipArchive;
use Rar;
use PharData;
use \OCP\IConfig;


class ExtractionController extends Controller {
	private $config;
	private $UserId;
	public function __construct(IConfig $config, $AppName, IRequest $request, string $UserId){
		parent::__construct($AppName, $request);
		$this->config = $config;
		$this->UserId = $UserId;
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */

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
    public function extractHere($nameOfFile, $directory, $external) {		
		$zip = new ZipArchive();
		if ($external){
			$externalMountPoints = $this->getExternalMP();
			foreach($externalMountPoints as $externalMP){
				if ($zip->open($externalMP.$directory.'/'.$nameOfFile) === TRUE) {
					$zip->extractTo($externalMP.$directory.'/');
					$zip->close();
					echo "ok";
					return;
				}
			}
			echo "ko";
		}else{
			echo "avant";
			echo $this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$nameOfFile;
			if ($zip->open($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$nameOfFile) === TRUE) {
				echo "apr§s";
				for($i = 0; $i < $zip->numFiles; $i++) {
					$zip->extractTo($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory, array($zip->getNameIndex($i)));
					self::scanFolder('/'.$this->UserId.'/files'.$directory.'/'.$zip->getNameIndex($i));					
				}
				$zip->close();
			}
		}
	}
	public function extractHereRar($nameOfFile, $directory, $external) {
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
					}else{
						exec("unrar x '".$externalMP.$directory."/".$nameOfFile."' -R '".$externalMP.$directory."' -o+",$output,$return);
					}
					echo "ok";
					return;
				}
			}
			echo "ko";
		}else{
			$file = $this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$nameOfFile;
			$dir = $this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory;
			if (extension_loaded ("rar")){
				$rar_file = rar_open($file);
				$list = rar_list($rar_file);
				var_dump($rar_file);
				foreach($list as $fileOpen) {
					$entry = rar_entry_get($rar_file, $fileOpen->getName());
					$entry->extract($dir); // extract to the current dir
					self::scanFolder('/'.$this->UserId.'/files'.$directory.'/'.$fileOpen->getName());
				}
				rar_close($rar_file); 
			}else{
				exec("unrar x \"".$file."\" -R \"".$dir."\" -o+",$output,$return);
				foreach ($output as $val ) {
					if(preg_split('/ /', $val, -1, PREG_SPLIT_NO_EMPTY)[0] == "Extracting" && 
					preg_split('/ /', $val, -1, PREG_SPLIT_NO_EMPTY)[1] != "from"){
						$fichier = substr(strrchr($PATH, "/"), 1);
						self::scanFolder('/'.$this->UserId.'/files'.$directory.'/'.$fichier);
					}
				}
			}
		}
	}
	public function extractHereOthers($nameOfFile, $directory, $external) {
		if ($external){
			$externalMountPoints = $this->getExternalMP();
			foreach($externalMountPoints as $externalMP){
				if (file_exists($externalMP.$directory."/".$nameOfFile)){
					exec("7z -y x '".$externalMP.$directory."/".$nameOfFile."' -o'".$externalMP.$directory."/".pathinfo($nameOfFile)['filename']."/'");
					echo "ok";
					echo "7z -y x '".$externalMP.$directory."/".$nameOfFile."' -o'".$externalMP.$directory."/".pathinfo($nameOfFile)['filename']."/'";
					return;
				}
			}
			echo "ko";
		}else{
			$file = $this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$nameOfFile;
			$dir = $this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.pathinfo($nameOfFile)['filename'];
			exec("7z -y x '".$file."' -o'".$dir."' ");
			echo "7z -y x '".$file."' -o'".$dir."' ";
			self::scanFolder('/'.$this->UserId.'/files'.$directory.'/'.pathinfo($nameOfFile)['filename']);
		}
	}
	protected function scanFolder($path)
    {
        $user = \OC::$server->getUserSession()->getUser()->getUID();
        $scanner = new \OC\Files\Utils\Scanner($user, \OC::$server->getDatabaseConnection(), \OC::$server->getLogger());
		try {
            $scanner->scan($path, $recusive = false);
        } catch (ForbiddenException $e) {
			echo $e;
        } catch (\Exception $e) {
			echo $e;
        }
    } 
}