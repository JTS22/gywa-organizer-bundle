<?php


use Contao\CoreBundle\DataContainer\PaletteManipulator;

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

PaletteManipulator::create()->addLegend('category_legend', 'title_legend')
    ->addField('category', 'category_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_page');
PaletteManipulator::create()->addLegend('category_legend', 'title_legend')
    ->addField('category', 'category_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('regular', 'tl_page');
PaletteManipulator::create()->addLegend('category_legend', 'title_legend')
    ->addField('category', 'category_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('forward', 'tl_page');
PaletteManipulator::create()->addLegend('category_legend', 'title_legend')
    ->addField('category', 'category_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('redirect', 'tl_page');

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