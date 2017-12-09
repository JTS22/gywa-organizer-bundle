<?php

namespace GyWa\OrganizerBundle;

use Contao\DataContainer;
use Contao\CoreBundle\Exception\InternalServerErrorException;
use Contao\Controller;
use Contao\Input;

class FileManager
{

    private $dbManager;

    public function __construct(DatabaseManager $manager)
    {
        $this->dbManager = $manager;
    }

    public function createFolderStructureForAlias(DataContainer $dc, $oldAlias){
        if (!empty($oldAlias) && $oldAlias != $dc->activeRecord->alias) {
            $this->dbManager->syncFilesWithDatabase();
            $newFilePath = $this->getFilePathUpToPageId($dc->activeRecord->id, $dc->activeRecord->alias);
            $oldFilePath = $this->dbManager->getDBFilePathForAlias($oldAlias);

            if ($oldFilePath->numRows > 0 && file_exists($oldFilePath->path)) {
                if ($oldFilePath->path != $newFilePath) {
                    $this->moveOrMergeFolders($oldFilePath->path, $newFilePath);
                }
            }
            $this->dbManager->syncFilesWithDatabase();
        } else {
            $this->dbManager->syncFilesWithDatabase();
            $newFilePath = $this->getFilePathUpToPageId($dc->activeRecord->id, $dc->activeRecord->alias);
            $oldFilePath = $this->dbManager->getDBFilePathForAlias($dc->activeRecord->alias);

            if ($oldFilePath->numRows > 0 && file_exists($oldFilePath->path)) {
                if ($oldFilePath->path != $newFilePath) {
                    $this->moveOrMergeFolders($oldFilePath->path, $newFilePath);
                }
            } else if (!$this->checkForFile($newFilePath)){
                if (!mkdir($newFilePath, 0777, true)) {
                    throw new InternalServerErrorException('New folder could not be created in ' . $newFilePath . '!');
                }
            }
            $this->dbManager->syncFilesWithDatabase();
        }


    }

    public function adaptFilesToPageStructure(DataContainer $dc) {
        if (\Input::get("key") == "adapt") {
            try {
                $this->dbManager->syncFilesWithDatabase();
                $pages = $this->dbManager->selectAllRegularPages();

                do {
                    $newFilePath = $this->getFilePathUpToPageId($pages->id, $pages->alias);
                    $oldFilePath = $this->dbManager->getDBFilePathForAlias($pages->alias);

                    if ($oldFilePath->numRows > 0 && file_exists($oldFilePath->path)) {
                        if ($oldFilePath->path != $newFilePath) {
                            $this->moveOrMergeFolders($oldFilePath->path, $newFilePath);
                        }
                    } else if (!$this->checkForFile($newFilePath)){
                        if (!mkdir($newFilePath, 0777, true)) {
                            throw new InternalServerErrorException('New folder could not be created in ' . $newFilePath . '!');
                        }
                    }
                } while ($pages = $pages->next());
                $this->dbManager->syncFilesWithDatabase();
            } catch (\Exception $exception) {
            }

            Controller::redirect('contao?do=files');
        }
    }

    public function organizeFiles(DataContainer $dc) {

        if (Input::get('do') == 'page')
        {

            if (Input::get('act') == 'cut') {

                $pageID = Input::get('id');
                $newParent = Input::get('pid');
                $mode = Input::get('mode');


                if ($pageID != false && $newParent != false && $mode != false) {

                    $this->dbManager->syncFilesWithDatabase();

                    if ($mode == 1) {
                        //Insert after newParent
                        $newpID = \Database::getInstance()->prepare("SELECT pID FROM tl_page WHERE id=?")->limit(1)->execute($newParent)->pID;
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

    public function checkFileName($varValue, DataContainer $dc) {
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
        } else if (!$this->checkForFile(substr($newPath, 0, strripos($newPath, '/')))) {
            if (!mkdir(substr($newPath, 0, strripos($newPath, '/')), 0777, true)) {
                throw new InternalServerErrorException('New folder could not be created in ' . $newPath . '!');
            }
        }
        if (!rename($oldPath, $newPath)) {
            throw new InternalServerErrorException('Folder ' . $oldPath . ' could not be moved to new path ' . $newPath . '!');
        }
    }

    public function moveToTrash($path) {
        $trashPath = GyWaFileConfig::$trashFileDirectory . date('Y-m-d-H-i-s') . '/' . substr($path, strlen(GyWaFileConfig::$rootFileDirectory), strlen($path) - strlen(GyWaFileConfig::$rootFileDirectory));
        if (!mkdir($trashPath, 0777, true) || !rename($path, $trashPath)) {
            throw new InternalServerErrorException('Occupying folder in ' . $path . ' could not be moved to trash!');
        }
    }

    public function resortFolder($id, $newpid) {
        $pageToMove = $this->dbManager->getAliasForID($id);
        $oldpid = $this->dbManager->requestParentPageForID($id);

        if ($oldpid != $newpid) {
            $newParentPage = $this->dbManager->getAliasForID($newpid);

            $oldFilePath = $this->dbManager->getDBFilePathForAlias($pageToMove);
            $newFilePath = $this->dbManager->getDBFilePathForAlias($newParentPage);

            if ($oldFilePath->numRows > 0) {
                if ($this->checkForFile($oldFilePath->path)) { //If the old path exists
                    if ($newFilePath->numRows == 0) { //The new file path does not exist (yet)
                        $newPath = $this->createFilePathUpToID($newpid, $newParentPage->alias);
                    } else {
                        $newPath = $newFilePath->path;

                        if (is_null($newPath) || empty($newPath)) {
                            throw new InternalServerErrorException('No directory found for page alias ' . $pageToMove->alias . '.');
                        } else if (!file_exists($newPath)) {
                            //The new file path does exist in the database but not in the file system
                            if(!mkdir(strtolower($newPath), 0777, true)) {
                                throw new InternalServerErrorException('Directory ' . $newPath . ' cannot be created or accessed.');
                            }
                        }
                    }

                    if ($this->checkForFile($newPath)) { //Both paths exist and can be written to!
                        if ($oldFilePath->path != ($newPath . '/' . $pageToMove)) {
                            $this->moveOrMergeFolders($oldFilePath->path, $newPath . '/' . $pageToMove);
                        }
                    } else {
                        throw new InternalServerErrorException('Directory ' . $newPath . ' cannot be accessed.');
                    }
                }
            }

            unset($oldFilePath, $newFilePath);
        }

    }

    public function checkForFile($path) {
        return (!empty($path) && file_exists($path) && is_dir($path) && is_writable($path));
    }

    public function getFilePathUpToPageId($id, $startAlias) {
        $path = '';
        $alias = $startAlias;

        while(($parent = $this->dbManager->requestParentPageForID($id)) && $parent->type != 'root') {
            $path = '/' . $alias . $path;
            $id = $parent->id;
            $alias = $parent->alias;
        }

        $path = GyWaFileConfig::$rootFileDirectory . $path;

        return $path;
    }

    public function createFilePathUpToID($id, $alias) {
        $path = $this->getFilePathUpToPageId($id, $alias);
        if (!$this->checkForFile($path)) {
            if(!mkdir(strtolower($path), 0777, true)) {
                throw new InternalServerErrorException('Directory ' . $path . ' cannot be accessed or created.');
            }
        }
        return $path;
    }

    public function removeFolderForAlias(DataContainer $dc) {
        $this->dbManager->syncFilesWithDatabase();
        $filePath = $this->dbManager->getDBFilePathForAlias($dc->activeRecord->alias);
        if ($filePath->numRows > 0 && $this->checkForFile($filePath->path)) {
            $this->moveToTrash($filePath->path);
        }
        $this->dbManager->syncFilesWithDatabase();
    }

}