<?php

namespace GyWa\OrganizerBundle;

use Contao\CoreBundle\Exception\InternalServerErrorException;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Contao\Dbafs;
use Contao\Database;
use Psr\Log\LoggerInterface;

class DatabaseManager
{
    private $database;
    private $logger;

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        $this->database = $connection;
        $this->logger = $logger;
    }

    public function syncFilesWithDatabase() {
        try {
            Dbafs::syncFiles();
        } catch (\Exception $exception) {
            throw new InternalServerErrorException('Database-File-Sync failed: ' . $exception->getMessage());
        }
    }

    public function checkForDistinctFileNames() {
        $duplicateFolders = Database::getInstance()->prepare("select path from tl_files WHERE name in (select name from tl_files where type='folder' AND path LIKE 'files/daten/%' AND (SELECT COUNT(*) FROM tl_page WHERE alias=name) > 0 group by name having count(*) > 1) AND type='folder' AND path LIKE 'files/daten/%' ORDER BY name ASC")->execute();
        return $duplicateFolders;
    }

    public function selectAllRegularPages() {
        return Database::getInstance()->prepare("SELECT `id`, `alias` FROM tl_page WHERE `type`='regular'")->execute();
    }

    public function requestParentPageForID($id) {
        return Database::getInstance()->prepare("SELECT `id`, `alias`, `type` FROM tl_page WHERE id=(SELECT pid FROM tl_page WHERE id=?)")->limit(1)->execute($id);
    }

    public function requestNewsArchiveFolder($archiveID) {

        $objArchive = \NewsArchiveModel::findById($archiveID);
        if (empty($objArchive) || empty($objArchive->fileLocation)) { //HOW?!?
            return false;
        }
        $objFile = \FilesModel::findByUuid($objArchive->fileLocation);
        if ($objFile === null)
        {
            return false;
        } else return $objFile->path;
    }

    public function requestPIDForID($id) {
        $result = PageModel::findByPk($id);
        if (!empty($result)) return $result->pid;
        return false;
    }

    public function getAliasForID($id) {
        $page = PageModel::findById($id);
        if (!empty($page)) return $page->alias;
        else return false;
    }

    public function pageExistsWithAlias($alias) {
        return PageModel::countByAlias($alias) > 0;
    }
}