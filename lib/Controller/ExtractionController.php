<?php
namespace OCA\Extract\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use ZipArchive;
//use \OC\Files\Cache\Scanner;
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

    public function extractHere($nameOfFile, $directory, $external) {
		$zip = new ZipArchive();
		if ($external){
			$good = false;
			$externalUrl = $this->config->getSystemValue('external', '');
			for ($i=0; $i < sizeof($externalUrl) && !$good; $i++){
				if ($zip->open($externalUrl[$i].$directory.'/'.$nameOfFile) === TRUE) {
					$zip->extractTo($externalUrl[$i].$directory.'/');
					$zip->close();
					$good = true;
				}
			}
		}else{
			if ($zip->open($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$nameOfFile) === TRUE) {
				for($i = 0; $i < $zip->numFiles; $i++) {
					$zip->extractTo($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory, array($zip->getNameIndex($i)));
					self::scanFolder('/'.$this->UserId.'/files'.$directory.$zip->getNameIndex($i));					
				}
				$zip->close();
			}
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
