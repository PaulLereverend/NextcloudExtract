<?php

namespace OCA\Extract\Controller;

use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorage;
use OCP\IRequest;
use OCP\AppFramework\Controller;
use Psr\Log\LoggerInterface;
use ZipArchive;
use Rar;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use OCP\IL10N;
use OC\Files\Filesystem;
use OCP\Encryption\IManager;

abstract class StatusCode {
    const ERROR = 0;
    const SUCCESS = 1;
}

class ExtractionController extends Controller {
    /** @var IL10N */
    private $l;

    /** @var IManager */
    protected IManager $encryptionManager;

    private $userId;

    /** @var LoggerInterface */
    private $logger;

    /** @var IRootFolder */
    private IRootFolder $rootFolder;

    /** @var string */
    private string $transactionId;

    public function __construct(
        $AppName,
        IRequest $request,
        IL10N $l,
        IManager $encryptionManager,
        $UserId,
        LoggerInterface $logger,
        IRootFolder $rootFolder
    ) {
        parent::__construct($AppName, $request);
        $this->l = $l;
        $this->encryptionManager = $encryptionManager;
        $this->userId = $UserId;
        $this->logger = $logger;
        $this->rootFolder = $rootFolder;

        $this->transactionId = uniqid('nextcloud_extract-');

        \OC_Util::tearDownFS();
        \OC_Util::setupFS($this->userId);
    }

    /**
     * CAUTION: the @Stuff turns off security checks; for this page no admin is
     *          required and no CSRF check. If you don't know what CSRF is, read
     *          it up in the docs or you might create a security hole. This is
     *          basically the only required method to add this exemption, don't
     *          add it to any other method if you don't exactly know what it does
     */
    /**
     * @param $sourcePath
     * @param $targetDirName
     * @param $type
     * @return array
     * @throws \OCP\Files\NotFoundException
     *
     * @NoCSRFRequired
     * @NoAdminRequired
     */
    public function extract($sourcePath, $targetDirName, $type) {
        if ($this->encryptionManager->isEnabled()) {
            return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Encryption is not supported yet'));
        }

        // Absolute file path to the local file, downloads tmp file if primary storage is external (S3)
        $absoluteFilePath = Filesystem::getView()->getLocalFile($sourcePath);
        $this->logger->error($absoluteFilePath);
        if ($absoluteFilePath === null) {
            return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Archive file not found'));
        }

        // Path to the file in Nextclouds internal filesystem
        $internalDir = dirname($sourcePath);
        
        $isExternal = $this->isExternalStorage($internalDir);

        // Tar gz files downloaded from external storage look like '/tmp/oc_tmp_LNmlJI-.gz' in $absoluteFilePath, so $sourcePath has to be used
        $isTarGz = self::isTarGz($sourcePath);

        // Make the target directory name
        $targetDirName = $this->sanitizeTargetPath($targetDirName);
        $fileNameWithoutExtension = self::getFileNameWithoutExtension($absoluteFilePath);

        if (empty($targetDirName)) {
            $targetDirName = $fileNameWithoutExtension;
        }

        // Path to the target folder in Nextclouds internal filesystem
        $internalTargetPath = "$internalDir/$targetDirName";

        // Error if the target folder already exists
        $folderExists = Filesystem::is_dir($internalTargetPath);
        if ($folderExists) {
            return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Directory already exists'));
        }

        // Path to the target folder in local filesystem
        $extractTo = dirname($absoluteFilePath) . '/' . $targetDirName;
        if ($isExternal) {
            $transactionDir = '/tmp/' . $this->transactionId;

            // Remove leading '/' from external storage path
            $internalDir = substr($internalDir, 0, 1) === '/' ? substr($internalDir, 1) : $internalDir;
            $targetDir = strlen($internalDir) > 0 ? "$internalDir/$targetDirName" : $targetDirName;

            $extractTo = "$transactionDir/$targetDir";
        }

        switch ($type) {
            case 'zip':
                $response = $this->extractZip($absoluteFilePath, $extractTo);
                break;
            case 'rar':
                $response = $this->extractRar($absoluteFilePath, $extractTo);
                break;
            default:
                $response = $this->extractOther($absoluteFilePath, $extractTo);

                // Extract .tar from .gz
                if ($isTarGz && $response['code'] == StatusCode::SUCCESS) {
                    // Extract .tar
                    $tarName = pathinfo($absoluteFilePath)['filename'];

                    $tarFilePath = $extractTo . '/' . $tarName;
                    $response = $this->extractOther($tarFilePath, $extractTo);

                    // Remove .tar file
                    unlink($tarFilePath);
                }
                break;
        }

        // Register the new files to the NC filesystem
        if ($isExternal) {
            $this->moveFromTmp();
        } else {
            Filesystem::mkdir($internalTargetPath);
        }

        $this->logger->info("Successfully extracted '$sourcePath' to '$internalTargetPath' ($this->transactionId)");
        return $response;
    }

    /**
     * Extracts a zip archive
     *
     * @param string $filePath absolute path to the source archive
     * @param string $extractTo absolute path to the extraction directory
     * @return array json response
     */
    public function extractZip(string $filePath, string $extractTo): array {
        if (!extension_loaded('zip')) {
            return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Zip extension is not available'));
        }

        $zip = new ZipArchive();

        if (!$zip->open($filePath) === true) {
            return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Cannot open Zip file'));
        }

        $zip->extractTo($extractTo);
        $zip->close();
        return array('code' => StatusCode::SUCCESS);
    }

    /**
     * Extracts a rar archive
     *
     * @param string $filePath absolute path to the source archive
     * @param string $extractTo absolute path to the extraction directory
     * @return array json response
     */
    public function extractRar(string $filePath, string $extractTo): array {
        if (!extension_loaded('rar')) {
            exec('unrar x ' . escapeshellarg($filePath) . ' -R ' . escapeshellarg($extractTo) . '/ -o+', $output, $return);

            if (sizeof($output) <= 4) {
                return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Oops something went wrong. Check that you have rar extension or unrar installed'));
            }
        } else {
            $rar_file = rar_open($filePath);
            $list = rar_list($rar_file);
            foreach ($list as $archive_file) {
                $entry = rar_entry_get($rar_file, $archive_file->getName());
                $entry->extract($extractTo);
            }
            rar_close($rar_file);
        }

        return array('code' => StatusCode::SUCCESS);
    }

    /**
     * Extracts a other archive (tar, tar.gz)
     *
     * @param string $filePath absolute path to the source archive
     * @param string $extractTo absolute path to the extraction directory
     * @return array json response
     */
    public function extractOther(string $filePath, string $extractTo): array
    {
        exec('7za -y x ' . escapeshellarg($filePath) . ' -o' . escapeshellarg($extractTo), $output, $return);

        if (sizeof($output) <= 5) {
            return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Oops something went wrong. Check that you have p7zip installed'));
        }

        return array('code' => StatusCode::SUCCESS);
    }

    /**
     * Sanitizes a raw target path
     *
     * @param string $dir raw path
     * @return string sanitized path
     */
    private function sanitizeTargetPath(string $dir): string
    {
        return trim(str_replace('../', '', $dir));
    }

    /**
     * Check if the given path is part of an external storage provider
     *
     * @param string $internalPath any path in the target storage
     * @return bool
     * @throws \OCP\Files\NotFoundException
     */
    public function isExternalStorage(string $internalPath): bool
    {
        return !$this->getStorage($internalPath)->isLocal();
    }

    /**
     * Get the storage interface at a given path
     *
     * @param string $internalPath any path in the target storage
     * @return IStorage
     * @throws \OCP\Files\NotFoundException
     */
    public function getStorage(string $internalPath): IStorage
    {
        $mountPointDir = Filesystem::getView()->getMountPoint($internalPath);
        return $this->rootFolder->get($mountPointDir)->getStorage();
    }

    /**
     * Moves the locally generated files from the /tmp/ folder from this transaction to the nextcloud storage.
     * This is the case if the file for this transaction is from an external storage (S3)
     */
    public function moveFromTmp(): void
    {
        $transactionDir = '/tmp/' . $this->transactionId . '/';

        $it = new RecursiveDirectoryIterator($transactionDir, FilesystemIterator::SKIP_DOTS);
        $it = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($it as $fileInfo) {
            if ($fileInfo->isFile()) {
                $tmpFilePath = $fileInfo->getPathname();
                $storageFilePath = '/' . substr($tmpFilePath, strlen($transactionDir));

                // move from tmp to nextcloud storage
                Filesystem::fromTmpFile($tmpFilePath, $storageFilePath);
            } elseif ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            }
        }
        rmdir($transactionDir);
    }

    /**
     * Returns the name of the file at the given path without its extension
     * @param string $path path to the file
     * @return string file name without extension
     */
    public static function getFileNameWithoutExtension(string $path): string
    {
        $fileName = pathinfo($path)['filename'];
        return self::isTarGz($path) ? pathinfo($fileName)['filename'] : $fileName;
    }

    /**
     * Checks if the file at the given path has the ending .tar.*
     * @param string $path path to the file
     * @return bool is tar.gz
     */
    public static function isTarGz(string $path): bool
    {
        $fileName = pathinfo($path)['filename'];
        return array_key_exists('extension', pathinfo($fileName)) && pathinfo($fileName)['extension'] == 'tar';
    }
}
