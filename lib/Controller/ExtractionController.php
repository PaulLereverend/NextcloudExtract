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
use OCP\Encryption\IManager;

class ExtractionController extends Controller {

	/** @var IL10N */
	private $l;

	/** @var IManager */
	protected $encryptionManager;

	public function __construct($AppName, IRequest $request, IL10N $l, IManager $encryptionManager){
		parent::__construct($AppName, $request);
		$this->l = $l;
		$this->encryptionManager = $encryptionManager;
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
		if ($this->encryptionManager->isEnabled()) {
			$response = array();
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Encryption is not supported yet")));
			return json_encode($response);
		}
		$file = $this->getFile($directory, $nameOfFile);
		$dir = dirname($file);
		//name of the file without extention
		$filename = pathinfo($nameOfFile)['filename'];
		$extractTo = $dir . '/' . $filename;
		$tmpPath = "/extract_tmp/" . $filename ;

		if(array_key_exists('extension', pathinfo($filename)) && pathinfo($filename)['extension'] == "tar"){
			$tmpPath = '/extract_tmp/' . pathinfo($filename)['filename'];
		}

		// if the file is un external storage
		if($external){
			$extractTo = Filesystem::getLocalFolder('/') . $tmpPath;
		}

		switch ($type) {
			case 'zip':
				$response = $this->extractZip($file, $filename, $extractTo);
				break;
			case 'rar':
				$response = $this->extractRar($file, $filename, $extractTo);
				break;
			default:
				// Check if the file is .tar.gz in order to do the extraction on a single step
				if(array_key_exists('extension', pathinfo($filename)) && pathinfo($filename)['extension'] == "tar"){
					$clean_filename = pathinfo($filename)['filename'];
					$extractTo = dirname($extractTo) . '/' . $clean_filename;
					$response = $this->extractOther($file, $clean_filename, $extractTo);
					$file = $extractTo . '/' . pathinfo($file)['filename'];
					$filename = $clean_filename;
					$response = $this->extractOther($file, $filename, $extractTo);
					
					// remove .tar file
					unlink($file);
				}else{
					$response = $this->extractOther($file, $filename, $extractTo);
				}
				break;
		}

		$this->postExtract($filename, $directory, $tmpPath, $external);
		return $response;
	}
	public function extractZip($file, $filename, $extractTo){
		$response = array();

		if (!extension_loaded("zip")){
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Zip extension is not available")));
			return json_encode($response);
		}

		$zip = new ZipArchive();

		if (!$zip->open($file) === TRUE){
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Can't open zip file ")));
			return json_encode($response);
		}

		$zip->extractTo($extractTo);
		$zip->close();
		$response = array_merge($response, array("code" => 1));
		return json_encode($response);
	}
	public function extractRar($file, $filename, $extractTo){
		$response = array();

		if (!extension_loaded("rar")){
			exec('unrar x ' .escapeshellarg($file). ' -R ' .escapeshellarg($extractTo). '/ -o+',$output,$return);
				if(sizeof($output) <= 4){
					$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Oops something went wrong. Check that you have rar extension or unrar installed")));
					return json_encode($response);
				}
		}else{
			$rar_file = rar_open($file);
			$list = rar_list($rar_file);
			foreach($list as $archive_file) {
				$entry = rar_entry_get($rar_file, $archive_file->getName());
				$entry->extract($extractTo);
			}
			rar_close($rar_file);
		}

		$response = array_merge($response, array("code" => 1));
		return json_encode($response);
	}
	public function extractOther($file, $filename, $extractTo){
		$response = array();

		exec('7za -y x ' .escapeshellarg($file). ' -o' .escapeshellarg($extractTo),$output,$return);

		if(sizeof($output) <= 5){
			$response = array_merge($response, array("code" => 0, "desc" => $this->l->t("Oops something went wrong. Check that you have p7zip installed")));
			error_log($output);

			return json_encode($response);
		}
		$response = array_merge($response, array("code" => 1));
		return json_encode($response);
	}

	//Register the new files to the NC filesystem
	public function postExtract($filename, $directory, $tmpPath, $external){
		$NCDestination = $directory . '/' . $filename;
		if($external){
			Filesystem::mkdir($tmpPath);
			Filesystem::rename($tmpPath, $NCDestination);
			Filesystem::rmdir(dirname($tmpPath));
		}else{
			Filesystem::mkdir($NCDestination);
		}
	}
}
