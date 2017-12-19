<?php

namespace GyWa\OrganizerBundle;

use Contao\DataContainer;
use Contao\CoreBundle\Exception\InternalServerErrorException;
use Contao\Controller;
use Contao\Files;
use Contao\Input;
use Psr\Log\LoggerInterface;

class FileManager
{

    private $dbManager;
    private $files;
    private $logger;

    public function __construct(DatabaseManager $manager, LoggerInterface $logger)
    {
        $this->dbManager = $manager;
        $this->files = Files::getInstance();
        $this->logger = $logger;

        if (\Input::get("key") == "adapt" || \Input::get("do") != "files") {
            $this->checkForNecessaryFolders();
        }

    }

    private function checkForNecessaryFolders() {
        if (!$this->checkForFile(GyWaFileConfig::$rootFileDirectory)) {
            throw new InternalServerErrorException('Folder "files/daten" does not exist in the current file-system or is not set to public. Please create the folder as well as the folder /files/trash for the GyWa-Organizer plugin to run.');
        }
        if (!$this->checkForFile(GyWaFileConfig::$trashFileDirectory)) {
            throw new InternalServerErrorException('Folder "files/trash" does not exist in the current file-system or is not set to public. Please create the folder as well as the folder /files/daten for the GyWa-Organizer plugin to run.');
        }
    }

    private function getActualFilePathForAlias($alias) {
        $result = glob(GyWaFileConfig::$rootFileDirectory . '{/**/' . $alias . ',/' . $alias . '}', GLOB_BRACE | GLOB_ONLYDIR | GLOB_NOSORT);
        if ($result == false) return false;
        else if (count($result) > 0) return $result[0];
        else return false;

    }

    public function createFolderStructureForAlias(DataContainer $dc, $oldAlias) {
        if (!empty($oldAlias) && $oldAlias != $dc->activeRecord->alias) {
            //$this->dbManager->syncFilesWithDatabase();
            $newFilePath = $this->getIntendedFilePathUpToPageId($dc->activeRecord->id, $dc->activeRecord->alias);
            $oldFilePath = $this->getActualFilePathForAlias($oldAlias);

            if (!empty($oldFilePath) && file_exists($oldFilePath)) {
                if ($oldFilePath != $newFilePath) {
                    $this->moveOrMergeFolders($oldFilePath, $newFilePath);
                    $this->dbManager->syncFilesWithDatabase();
                }
            }
        } else {
            //$this->dbManager->syncFilesWithDatabase();
            $newFilePath = $this->getIntendedFilePathUpToPageId($dc->activeRecord->id, $dc->activeRecord->alias);
            $oldFilePath = $this->getActualFilePathForAlias($dc->activeRecord->alias);

            $this->logger->info('Path found for alias: ' . $oldFilePath);

            if (!empty($oldFilePath) && file_exists($oldFilePath)) {
                if ($oldFilePath != $newFilePath) {
                    $this->moveOrMergeFolders($oldFilePath, $newFilePath);
                    $this->dbManager->syncFilesWithDatabase();
                }
            } else if (!$this->checkForFile($newFilePath)){
                if (!mkdir($newFilePath, 0777, true)) {
                    throw new InternalServerErrorException('New folder could not be created in ' . $newFilePath . '!');
                }
                $this->dbManager->syncFilesWithDatabase();
            }
        }


    }

    public function adaptFilesToPageStructure() {
        if (\Input::get("key") == "adapt") {
            try {
                $this->dbManager->syncFilesWithDatabase();

                $duplicateFolders = $this->dbManager->checkForDistinctFileNames();
                if (!empty($duplicateFolders) && $duplicateFolders->numRows > 0) {
                    $folders = '';
                    foreach($duplicateFolders->fetchAllAssoc() as $folder) {
                        $folders .= $folder['path'] . ';';
                    }
                    $this->logger->error('Duplicate folders in your file system: ');
                    foreach($duplicateFolders->fetchAllAssoc() as $folder) {
                        $this->logger->error($folder['path']);
                    }
                    throw new InternalServerErrorException('There are duplicate folders in your file-system! Please make sure every folder is named like the page alias it belongs to! A list of duplicate folders has been written to your log file.');
                }

                $pages = $this->dbManager->selectAllRegularPages();
                if ($pages->numRows > 0) {
                    do {
                        $newFilePath = $this->getIntendedFilePathUpToPageId($pages->id, $pages->alias);
                        $oldFilePath = $this->getActualFilePathForAlias($pages->alias);

                        $this->logger->info('Checking page ' . $pages->alias);

                        if (!empty($oldFilePath) && file_exists($oldFilePath)) {
                            $this->logger->info('Found old path ' . $oldFilePath . '. New path should be ' . $newFilePath . '.');
                            if ($oldFilePath != $newFilePath) {
                                $this->logger->info('Trying to move...');
                                $this->moveOrMergeFolders($oldFilePath, $newFilePath);
                            }
                        } else if (!$this->checkForFile($newFilePath)){
                            $this->logger->info('No old path found... creating new Path ' . $newFilePath . '.');
                            if (!mkdir($newFilePath, 0777, true)) {
                                throw new InternalServerErrorException('New folder could not be created in ' . $newFilePath . '!');
                            }
                        }

                        $this->logger->info('Page ' . $pages->alias . ' completed!');
                    } while ($pages->next());
                    $this->dbManager->syncFilesWithDatabase();
                }
                unset($pages);

                $this->cleanupFoldersAndFiles();
                $this->dbManager->syncFilesWithDatabase();

            } catch (\Exception $exception) {
                throw new InternalServerErrorException('Error occurred while adapting file structure: ' . $exception->getMessage());
            }

            Controller::redirect('contao?do=files');
        }
    }

    private function cleanupFoldersAndFiles() {
        $path = GyWaFileConfig::$rootFileDirectory;
        $toRemove = array();
        $rdi = new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::KEY_AS_PATHNAME | \RecursiveDirectoryIterator::SKIP_DOTS);
        foreach (new \RecursiveIteratorIterator($rdi, \RecursiveIteratorIterator::CHILD_FIRST) as $file => $info) {
            if ($info->isDir() && count(scandir($file)) == 2) {
                array_push($toRemove, array('path' => $file, 'name' => $info->getFilename()));
            }
        }
        foreach ($toRemove as $fileArray) {
            if (file_exists($fileArray['path']) && !$this->dbManager->pageExistsWithAlias($fileArray['name'])) {
                $this->files->rmdir($fileArray['path']);
            }
        }
    }

    public function organizeFiles() {

        if (Input::get('do') == 'page')
        {

            if (Input::get('act') == 'cut') {

                $pageID = Input::get('id');
                $newParent = Input::get('pid');
                $mode = Input::get('mode');


                if ($pageID != false && $newParent != false && $mode != false) {

                    //$this->dbManager->syncFilesWithDatabase();

                    if ($mode == 1) {
                        //Insert after newParent
                        $newpID = $this->dbManager->requestPIDForID($newParent);

                        $this->resortFolder($pageID, $newpID);
                        unset($newpID);
                    } else if ($mode == 2) {
                        //Insert into newParent
                        $this->resortFolder($pageID, $newParent);
                    }

                    $this->dbManager->syncFilesWithDatabase();
                }

            }

        }
    }

    public function checkFileName($varValue) {
        $varValue = str_replace(['_', '.', '/', '\\', ' '], '-', $varValue);
        $varValue = strtolower($varValue);
        if (preg_match('/^[a-z0-9-]+$/', $varValue)) {
            return $varValue;
        } else {
            throw new InternalServerErrorException($GLOBALS['TL_LANG']['tl_files']['error_invalid_filename']);
        }
    }

    public function moveOrMergeFolders($oldPath, $newPath) {
        if ($this->checkForFile($newPath)) { //If already a folder exists in the new spot...
            $this->moveToTrash($newPath);
        } else if (!$this->checkForFile(dirname($newPath))) {
            if (!mkdir(dirname($newPath), 0777, true)) {
                throw new InternalServerErrorException('New folder could not be created in ' . dirname($newPath) . '!');
            }
        }
        if (!$this->files->rename($oldPath, $newPath)) {
            throw new InternalServerErrorException('Folder ' . $oldPath . ' could not be moved to new path ' . $newPath . '!');
        }
    }

    public function moveToTrash($path) {
        $trashPath = GyWaFileConfig::$trashFileDirectory . date('Y-m-d-H-i-s') . substr($path, strlen(GyWaFileConfig::$rootFileDirectory), strlen($path) - strlen(GyWaFileConfig::$rootFileDirectory));

        $this->logger->info('Moving ' . $path . ' to trash path ' . $trashPath);

        if (!$this->files->rename($path, $trashPath)) {
            throw new InternalServerErrorException('Occupying folder in ' . $path . ' could not be moved to trash!');
        }
    }

    public function resortFolder($id, $newpid) {
        $pageToMove = $this->dbManager->getAliasForID($id);
        $oldpid = $this->dbManager->requestPIDForID($id);

        if ($oldpid != $newpid) {
            $newParentPage = $this->dbManager->getAliasForID($newpid);

            $oldFilePath = $this->getActualFilePathForAlias($pageToMove);
            $newFilePath = $this->getActualFilePathForAlias($newParentPage);

            if (!empty($oldFilePath)) {
                if ($this->checkForFile($oldFilePath)) { //If the old path exists
                    if (empty($newFilePath)) { //The new file path does not exist (yet)
                        $newPath = $this->createFilePathUpToID($newpid, $newParentPage);
                    } else {
                        $newPath = $newFilePath;
                        if (!file_exists($newPath)) {
                            //The new file path does exist in the database but not in the file system
                            if(!mkdir(strtolower($newPath), 0777, true)) {
                                throw new InternalServerErrorException('Directory ' . $newPath . ' cannot be created or accessed.');
                            }
                        }
                    }

                    if ($this->checkForFile($newPath)) { //Both paths exist and can be written to!
                        if ($oldFilePath != ($newPath . '/' . $pageToMove)) {
                            $this->moveOrMergeFolders($oldFilePath, $newPath . '/' . $pageToMove);
                        }
                    } else {
                        throw new InternalServerErrorException('Directory ' . $newPath . ' cannot be accessed.');
                    }
                }
            }

        }

    }

    public function checkForFile($path) {
        return (!empty($path) && file_exists($path) && is_dir($path) && $this->files->is_writeable($path));
    }

    public function getIntendedFilePathUpToPageId($id, $startAlias) {
        $path = '/' . $startAlias;

        while(!empty(($parent = $this->dbManager->requestParentPageForID($id))) && $parent->type != 'root') {
            $path = '/' . $parent->alias . $path;
            $id = $parent->id;
        }

        $path = GyWaFileConfig::$rootFileDirectory . $path;

        return $path;
    }

    public function createFilePathUpToID($id, $alias) {
        $path = $this->getIntendedFilePathUpToPageId($id, $alias);
        if (!$this->checkForFile($path)) {
            if(!mkdir(strtolower($path), 0777, true)) {
                throw new InternalServerErrorException('Directory ' . $path . ' cannot be accessed or created.');
            }
        }
        return $path;
    }

    public function removeFolderForAlias(DataContainer $dc) {
       // $this->dbManager->syncFilesWithDatabase();
        $filePath = $this->getActualFilePathForAlias($dc->activeRecord->alias);
        if (!empty($filePath) && $this->checkForFile($filePath)) {
            $this->moveToTrash($filePath);
            $this->dbManager->syncFilesWithDatabase();
        }
    }

}