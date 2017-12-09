<?php

namespace GyWa\OrganizerBundle;

use Contao\CoreBundle\Exception\InternalServerErrorException;
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

    public function selectAllRegularPages() {
        return Database::getInstance()->prepare("SELECT * FROM tl_page WHERE type='regular'")->execute();
    }

    public function requestParentPageForID($id) {
        if($parentPage = Database::getInstance()->prepare("SELECT id, alias FROM tl_page WHERE id=(SELECT pid FROM tl_page WHERE id=?)")->limit(1)->execute($id)) {
            if ($parentPage->numRows > 0) return $parentPage;
            else return false;
        }
        return false;
    }

    public function getAliasForID($id) {
        return Database::getInstance()->prepare("SELECT alias FROM tl_page WHERE id=?")->limit(1)->execute($id)->alias;
    }

    public function getDBFilePathForAlias($alias) {
        return Database::getInstance()->prepare("SELECT `path` FROM tl_files WHERE `name`=? AND type = 'folder' AND `path` LIKE 'files/daten%'")->limit(1)->execute($alias);
    }
}