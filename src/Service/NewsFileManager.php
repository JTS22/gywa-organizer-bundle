<?php
/**
 * Created by PhpStorm.
 * User: Jonas
 * Date: 18.03.2018
 * Time: 14:30
 */

namespace GyWa\OrganizerBundle;

use Contao\CoreBundle\Exception\InternalServerErrorException;
use Contao\DataContainer;
use Contao\Files;
use Contao\System;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

class NewsFileManager
{

    private $dbManager;
    private $files;
    private $logger;
    private $fileManager;

    public function __construct(DatabaseManager $manager, LoggerInterface $logger)
    {
        $this->dbManager = $manager;
        $this->files = Files::getInstance();
        $this->logger = $logger;
        $this->fileManager = System::getContainer()->get('gywaorganizer.filemanager');
    }

    private function getRootDirectory($detailPageAlias) {
        if(!$this->fileManager->getActualFilePathForAlias($detailPageAlias)) {
            throw new InternalServerErrorException('File path for news archive not found in the file system, please synchronize your page and file structure first!');
        }
        return $this->fileManager->getActualFilePathForAlias($detailPageAlias);
    }

    public function createFolderStructureForNews(DataContainer $dc, $oldAlias) {
        if (!empty($oldAlias) && $oldAlias != $dc->activeRecord->alias) {
            $newFilePath = $this->getIntendedFilePathForNews($dc);
            $oldFilePath = $this->getActualFilePathForNews($oldAlias, $dc->activeRecord->id);

            if (!empty($oldFilePath) && file_exists($oldFilePath)) {
                if ($oldFilePath != $newFilePath) {
                    $this->fileManager->moveOrMergeFolders($oldFilePath, $newFilePath);
                    $this->dbManager->syncFilesWithDatabase();
                }
            }
        } else {
            $newFilePath = $this->getIntendedFilePathForNews($dc);
            $oldFilePath = $this->getActualFilePathForNews($dc->activeRecord->alias, $dc->activeRecord->id);

            $this->logger->info('Path found for alias: ' . $oldFilePath);

            if (!empty($oldFilePath) && file_exists($oldFilePath)) {
                if ($oldFilePath != $newFilePath) {
                    $this->fileManager->moveOrMergeFolders($oldFilePath, $newFilePath);
                    $this->dbManager->syncFilesWithDatabase();
                }
            } else if (!$this->fileManager->checkForFile($newFilePath)){
                if (!mkdir($newFilePath, 0777, true)) {
                    throw new InternalServerErrorException('New folder could not be created in ' . $newFilePath . '!');
                }
                $this->dbManager->syncFilesWithDatabase();
            }
        }


    }

    private function getDetailPageAlias($newsID) {
        $detailPageAlias = $this->dbManager->requestNewsArchiveDetailPage($newsID);

        if(empty($detailPageAlias) || $detailPageAlias->numRows == 0) {
            throw new InternalServerErrorException('No archive page found for this news! Please set the reference page for the corresponding news archive first.');
        }
        return $detailPageAlias->alias;
    }

    public function getIntendedFilePathForNews($dc) {
        $path = $this->getRootDirectory($this->getDetailPageAlias($dc->activeRecord->id));
        $path .= '/' . date('Y', $dc->activeRecord->date);
        $path .= '/' . date('m', $dc->activeRecord->date);
        $path .= '/' . $dc->activeRecord->newsAlias;

        return $path;
    }

    public function createFilePathForNews($dc) {
        $path = $this->getIntendedFilePathForNews($dc);
        if (!$this->fileManager->checkForFile($path)) {
            if(!mkdir(strtolower($path), 0777, true)) {
                throw new InternalServerErrorException('Directory ' . $path . ' cannot be accessed or created.');
            }
        }
        return $path;
    }

    public function getActualFilePathForNews($alias, $newsID) {
        $finder = new Finder();
        $finder->in($this->getRootDirectory($this->getDetailPageAlias($newsID)))->directories()->followLinks()->name($alias);
        if ($finder->count() > 0) {
            foreach ($finder as $dir) {
                return $dir;
            }
        }
        return false;
    }

    public function removeFolderForAlias(DataContainer $dc) {
        $filePath = $this->getActualFilePathForNews($dc->activeRecord->alias, $dc->activeRecord->id);
        if (!empty($filePath) && $this->fileManager->checkForFile($filePath)) {
            $this->fileManager->moveToTrash($filePath);
            $this->dbManager->syncFilesWithDatabase();
        }
    }

}