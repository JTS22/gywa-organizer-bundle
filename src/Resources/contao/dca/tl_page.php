<?php


array_push($GLOBALS['TL_DCA']['tl_page']['config']['onload_callback'], array('gywaorganizer.filemanager', 'organizeFiles'));
array_push($GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'], array('PageOrganizer', 'createFolderStructureForAlias'));
array_push($GLOBALS['TL_DCA']['tl_page']['config']['ondelete_callback'], array('gywaorganizer.filemanager', 'removeFolderForAlias'));
array_push($GLOBALS['TL_DCA']['tl_page']['fields']['alias']['save_callback'], array('PageOrganizer', 'checkPageAlias'));

$GLOBALS['TL_DCA']['tl_page']['fields']['category'] = array(
    'label' => $GLOBALS['TL_LANG']['tl_page']['category'],
    'inputType' => 'select',
    'foreignKey' => 'tl_category.title',
    'eval' => array('includeBlankOption'=>true),
    'relation' => array('type'=>'hasOne', 'load'=>'lazy'),
    'sql' => "int(10) unsigned NOT NULL default '0'"
);

function str_insert($str, $search, $insert) {
    $index = strpos($str, $search);
    if($index === false) {
        return $str;
    }
    return substr_replace($str, $search.$insert, $index, strlen($search));
}

$GLOBALS['TL_DCA']['tl_page']['palettes']['default'] = str_insert($GLOBALS['TL_DCA']['tl_page']['palettes']['default'], 'type;', '{category_legend},category;');
$GLOBALS['TL_DCA']['tl_page']['palettes']['regular'] = str_insert($GLOBALS['TL_DCA']['tl_page']['palettes']['regular'], 'type;', '{category_legend},category;');
$GLOBALS['TL_DCA']['tl_page']['palettes']['forward'] = str_insert($GLOBALS['TL_DCA']['tl_page']['palettes']['forward'], 'type;', '{category_legend},category;');
$GLOBALS['TL_DCA']['tl_page']['palettes']['redirect'] = str_insert($GLOBALS['TL_DCA']['tl_page']['palettes']['redirect'], 'type;', '{category_legend},category;');

class PageOrganizer {

    private $oldAlias;


    public function checkPageAlias($varValue, DataContainer $dc)
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

	public function createFolderStructureForAlias(DataContainer $dc) {
        $fileManager = \Contao\System::getContainer()->get('gywaorganizer.filemanager');
        $fileManager->createFolderStructureForAlias($dc, $this->oldAlias);
    }

}
?>