<?php
namespace OCA\Extract\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\Files\NotFoundException;
use OCP\Files\IRootFolder;
use ZipArchive;
use Rar;
use PharData;
use \OCP\IConfig;
use OCP\IL10N;
use OCP\EventDispatcher\IEventDispatcher;
use OC\Files\Filesystem;
use OCP\Encryption\IManager;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use OCA\Extract\Service\ExtractionService;

class ExtractionController extends Controller {

	/** @var IL10N */
	private $l;

	/** @var LoggerInterface */
	private $logger;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IManager */
	protected $encryptionManager;

	/** @var string */
	private $userId;

	/**  @var ExtractionService */
	private $extractionService;

	public function __construct(
		string $AppName
		, IRequest $request
		, ExtractionService $extractionService
		, IRootFolder $rootFolder
		, IL10N $l
		, LoggerInterface $logger
		, IManager $encryptionManager
		, $UserId
	){
		parent::__construct($AppName, $request);
		$this->l = $l;
		$this->logger = $logger;
		$this->encryptionManager = $encryptionManager;
		$this->userId = $UserId;
		$this->extractionService = $extractionService;
		$this->rootFolder = $rootFolder;
	}

	private function getFile($directory, $fileName){
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$fileNode = $userFolder->get($directory . '/' . $fileName);
		return $fileNode->getStorage()->getLocalFile($fileNode->getInternalPath());
	}

	//Register the new files to the NC filesystem
	private static function postExtract($filename, $directory, $tmpPath, $external){
		$NCDestination = $directory . '/' . $filename;
		if($external){
			Filesystem::mkdir($tmpPath);
			Filesystem::rename($tmpPath, $NCDestination);
			Filesystem::rmdir(dirname($tmpPath));
		}else{
			Filesystem::mkdir($NCDestination);
		}
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
			return new DataResponse($response);
		}
		$file = $this->getFile($directory, $nameOfFile);
		$dir = dirname($file);
		//name of the file without extension
		$filename = pathinfo($nameOfFile, PATHINFO_FILENAME);
		$extractTo = $dir . '/' . $filename;
		$tmpPath = "/extract_tmp/" . $filename ;

		if(pathinfo($filename, PATHINFO_EXTENSION) == "tar"){
			$tmpPath = '/extract_tmp/' . pathinfo($filename, PATHINFO_FILENAME);
		}

		// if the file is un external storage
		if($external){
			$extractTo = Filesystem::getLocalFolder('/') . $tmpPath;
		}

		switch ($type) {
			case 'zip':
				$response = $this->extractionService->extractZip($file, $filename, $extractTo);
				break;
			case 'rar':
				$response = $this->extractionService->extractRar($file, $filename, $extractTo);
				break;
			default:
				// Check if the file is .tar.gz in order to do the extraction on a single step
				if(pathinfo($filename, PATHINFO_EXTENSION) == "tar"){
					$clean_filename = pathinfo($filename, PATHINFO_FILENAME);
					$extractTo = dirname($extractTo) . '/' . $clean_filename;
					$response = $this->extractionService->extractOther($file, $clean_filename, $extractTo);
					$file = $extractTo . '/' . pathinfo($file, PATHINFO_FILENAME);
					$filename = $clean_filename;
					$response = $this->extractionService->extractOther($file, $filename, $extractTo);

					// remove .tar file
					unlink($file);
				}else{
					$response = $this->extractionService->extractOther($file, $filename, $extractTo);
				}
				break;
		}

		self::postExtract($filename, $directory, $tmpPath, $external);

		return new DataResponse($response);
	}
}
