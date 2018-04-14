<?php


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

    private function getRootDirectory(DataContainer $dc) {
        $detailFilePath = $this->dbManager->requestNewsArchiveFolder($dc->activeRecord->pid);
        if(empty($detailFilePath)) {
            throw new InternalServerErrorException('No existing file path found linked to current news archive. Be sure to properly configure the file path in the archive\'s settings');
        }
        return $detailFilePath;
    }

    public function createFolderStructureForNews(DataContainer $dc, $oldAlias, $oldDate) {
        if ((!empty($oldAlias) && $oldAlias != $dc->activeRecord->alias) || (!empty($oldDate) && $oldDate != $dc->activeRecord->date)) {
            $newFilePath = $this->getIntendedFilePathForNews($dc);
            $oldFilePath = $this->getActualFilePathForNews($dc, $oldAlias);

            if (!empty($oldFilePath) && file_exists($oldFilePath)) {
                if ($oldFilePath != $newFilePath) {
                    $this->fileManager->moveOrMergeFolders($oldFilePath, $newFilePath);
                    $this->dbManager->syncFilesWithDatabase();
                }
            }
        } else {
            $newFilePath = $this->getIntendedFilePathForNews($dc);
            $oldFilePath = $this->getActualFilePathForNews($dc, $dc->activeRecord->alias);

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

    public function getIntendedFilePathForNews(DataContainer $dc) {
        $path = $this->getRootDirectory($dc);
        $path .= '/' . date('Y', $dc->activeRecord->date);
        $path .= '/' . date('m', $dc->activeRecord->date);
        $path .= '/' . $dc->activeRecord->alias;

        return $path;
    }

    public function createFilePathForNews(DataContainer $dc) {
        $path = $this->getIntendedFilePathForNews($dc);
        if (!$this->fileManager->checkForFile($path)) {
            if(!mkdir(strtolower($path), 0777, true)) {
                throw new InternalServerErrorException('Directory ' . $path . ' cannot be accessed or created.');
            }
        }
        return $path;
    }

    public function getActualFilePathForNews(DataContainer $dc, $alias) {
        $finder = new Finder();
        $finder->in($this->getRootDirectory($dc))->directories()->followLinks()->name($alias);
        if ($finder->count() > 0) {
            foreach ($finder as $dir) {
                return $dir;
            }
        }
        return false;
    }

    public function removeFolderForAlias(DataContainer $dc) {
        $filePath = $this->getActualFilePathForNews($dc, $dc->activeRecord->alias);
        if (!empty($filePath) && $this->fileManager->checkForFile($filePath)) {
            $this->fileManager->moveToTrash($filePath);
            $this->dbManager->syncFilesWithDatabase();
        }
    }

}