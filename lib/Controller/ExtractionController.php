<?php

namespace OCA\Extract\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use ZipArchive;
use Rar;
use OCP\IL10N;
use OC\Files\Filesystem;
use OCP\Encryption\IManager;

abstract class StatusCode
{
    const ERROR = 0;
    const SUCCESS = 1;
}

class ExtractionController extends Controller
{
    /** @var IL10N */
    private $l;

    /** @var IManager */
    protected $encryptionManager;

    private $userId;

    public function __construct($AppName, IRequest $request, IL10N $l, IManager $encryptionManager, $UserId)
    {
        parent::__construct($AppName, $request);
        $this->l = $l;
        $this->encryptionManager = $encryptionManager;
        $this->userId = $UserId;

        \OC_Util::tearDownFS();
        \OC_Util::setupFS($this->userId);
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

    public function extract($sourceFileName, $targetDirName, $directory, $external, $type)
    {
        if ($this->encryptionManager->isEnabled()) {
            return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Encryption is not supported yet'));
        }
        // Protects before relative paths
        $targetDirName = str_replace('../', '', $targetDirName);
        $targetDirName = trim($targetDirName);

        $sourceFilePath = Filesystem::getLocalFile($directory . '/' . $sourceFileName);
        $dir = dirname($sourceFilePath);

        // name of the file without extension
        $fileNameWithoutExtension = pathinfo($sourceFileName)['filename'];
        $isTarGz = array_key_exists('extension', pathinfo($fileNameWithoutExtension)) && pathinfo($fileNameWithoutExtension)['extension'] == 'tar';
        if ($isTarGz) {
            $fileNameWithoutExtension = pathinfo($fileNameWithoutExtension)['filename'];
        }

        $targetName = $fileNameWithoutExtension;

        if ($targetDirName !== $fileNameWithoutExtension && !empty($targetDirName)) {
            $targetName = $targetDirName;
        }

        $extractTo = $dir . '/' . $targetName;
        $tmpPath = '/extract_tmp/' . $targetName;
        $folderExists = Filesystem::is_dir($directory . '/' . $targetName);

        if ($folderExists) {
            return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Directory already exists'), 'dir' => $extractTo);
        }

        // if the file is an external storage
        if ($external) {
            $extractTo = Filesystem::getLocalFolder('/') . $tmpPath;
        }

        switch ($type) {
            case 'zip':
                $response = $this->extractZip($sourceFilePath, $extractTo);
                break;
            case 'rar':
                $response = $this->extractRar($sourceFilePath, $extractTo);
                break;
            default:
                $response = $this->extractOther($sourceFilePath, $extractTo);

                // Extract .tar from .gz
                if ($isTarGz && $response['code'] == StatusCode::SUCCESS) {
                    // Extract .tar
                    $tarName = pathinfo($sourceFilePath)['filename'];
                    $tarFilePath = $extractTo . '/' . $tarName;
                    $response = $this->extractOther($tarFilePath, $extractTo);

                    // Remove .tar file
                    unlink($tarFilePath);
                }
                break;
        }

        $this->postExtract($targetName, $directory, $tmpPath, $external);
        return $response;
    }

    public function extractZip($file, $extractTo)
    {
        if (!extension_loaded('zip')) {
            return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Zip extension is not available'));
        }

        $zip = new ZipArchive();

        if (!$zip->open($file) === TRUE) {
            return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Cannot open Zip file'));
        }

        $zip->extractTo($extractTo);
        $zip->close();
        return array('code' => StatusCode::SUCCESS);
    }

    public function extractRar($file, $extractTo)
    {
        if (!extension_loaded('rar')) {
            exec('unrar x ' . escapeshellarg($file) . ' -R ' . escapeshellarg($extractTo) . '/ -o+', $output, $return);
            if (sizeof($output) <= 4) {
                return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Oops something went wrong. Check that you have rar extension or unrar installed'));
            }
        } else {
            $rar_file = rar_open($file);
            $list = rar_list($rar_file);
            foreach ($list as $archive_file) {
                $entry = rar_entry_get($rar_file, $archive_file->getName());
                $entry->extract($extractTo);
            }
            rar_close($rar_file);
        }

        return array('code' => StatusCode::SUCCESS);
    }

    public function extractOther($file, $extractTo)
    {
        exec('7za -y x ' . escapeshellarg($file) . ' -o' . escapeshellarg($extractTo), $output, $return);

        if (sizeof($output) <= 5) {
            return array('code' => StatusCode::ERROR, 'desc' => $this->l->t('Oops something went wrong. Check that you have p7zip installed'));
        }

        return array('code' => StatusCode::SUCCESS);
    }

    //Register the new files to the NC filesystem
    public function postExtract($filename, $directory, $tmpPath, $external)
    {
        $NCDestination = $directory . '/' . $filename;
        if ($external) {
            Filesystem::mkdir($tmpPath);
            Filesystem::rename($tmpPath, $NCDestination);
            Filesystem::rmdir(dirname($tmpPath));
        } else {
            Filesystem::mkdir($NCDestination);
        }
    }
}
