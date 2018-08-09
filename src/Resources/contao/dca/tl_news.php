<?php

array_push($GLOBALS['TL_DCA']['tl_news']['config']['onsubmit_callback'], array('NewsOrganizer', 'createFolderStructureForNews'));
array_push($GLOBALS['TL_DCA']['tl_news']['config']['ondelete_callback'], array('gywaorganizer.newsfilemanager', 'removeFolderForAlias'));
array_push($GLOBALS['TL_DCA']['tl_news']['fields']['alias']['save_callback'], array('NewsOrganizer', 'checkNewsAlias'));

if (is_array($GLOBALS['TL_DCA']['tl_news']['fields']['date']['save_callback'])) {
    array_push($GLOBALS['TL_DCA']['tl_news']['fields']['date']['save_callback'], array('NewsOrganizer', 'saveOldDate'));
} else {
    $GLOBALS['TL_DCA']['tl_news']['fields']['date']['save_callback'] = array(array('NewsOrganizer', 'saveOldDate'));
}

class NewsOrganizer
{

    private $oldAlias;
    private $oldDate;

    public function saveOldDate($varValue, DataContainer $dc)
    {
        $this->oldDate = $dc->activeRecord->date;
        return $varValue;
    }

    public function checkNewsAlias($varValue, DataContainer $dc)
    {
        $this->oldAlias = $dc->activeRecord->alias;
        $varValue = str_replace(['_', '.', '/', '\\', ' '], '-', $varValue);
        $varValue = strtolower($varValue);
        if (preg_match('/^[a-z0-9-]+$/', $varValue)) {
            return $varValue;
        } else {
            throw new Contao\CoreBundle\Exception\InternalServerErrorException($GLOBALS['TL_LANG']['tl_page']['error_invalid_alias']);
        }
    }

    public function createFolderStructureForNews(DataContainer $dc)
    {
        $fileManager = \Contao\System::getContainer()->get('gywaorganizer.newsfilemanager');
        $fileManager->createFolderStructureForNews($dc, $this->oldAlias, $this->oldDate);
    }

}

?>