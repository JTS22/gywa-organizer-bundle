<?php

namespace GyWa\OrganizerBundle;

use Contao\CoreBundle\Exception\InternalServerErrorException;
use Contao\FilesModel;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Contao\Dbafs;
use Contao\Database;

class DatabaseManager
{
    private $database;

    public function __construct(Connection $connection)
    {
        $this->database = $connection;
    }

    public function syncFilesWithDatabase() {
        try {
            Dbafs::syncFiles();
        } catch (\Exception $exception) {
            throw new InternalServerErrorException('Database-File-Sync failed: ' . $exception->getMessage());
        }
    }

    public function checkForDistinctFileNames() {
        $duplicateFolders = Database::getInstance()->prepare("select path from tl_files WHERE name in (select name from tl_files where type='folder' AND path LIKE 'files/daten/%' group by name having count(*) > 1) AND type='folder' AND path LIKE 'files/daten/%' ORDER BY name ASC")->execute();
        return $duplicateFolders;
    }

    public function selectAllRegularPages() {
        return Database::getInstance()->prepare("SELECT `id`, `alias` FROM tl_page WHERE `type`='regular'")->execute();
    }

    public function requestParentPageForID($id) {
        return Database::getInstance()->prepare("SELECT `id`, `alias`, `type` FROM tl_page WHERE id=(SELECT pid FROM tl_page WHERE id=?)")->limit(1)->execute($id);
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

    public function getDBFilePathForAlias($alias) {
        $result = Database::getInstance()->prepare("SELECT `path` FROM tl_files WHERE `name`=? AND type = 'folder' AND `path` LIKE 'files/daten%'")->limit(1)->execute($alias);
        if ($result->numRows > 0) return $result->path;
        else return false;
    }

    public function pageExistsWithAlias($alias) {
        return PageModel::countByAlias($alias) > 0;
    }
}