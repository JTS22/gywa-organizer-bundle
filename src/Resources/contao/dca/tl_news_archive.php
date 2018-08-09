<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

$GLOBALS['TL_DCA']['tl_news_archive']['fields']['fileLocation'] = array
(
    'label' => &$GLOBALS['TL_LANG']['tl_news_archive']['fileLocation'],
    'inputType' => 'fileTree',
    'eval' => array('files' => false, 'fieldType' => 'radio', 'mandatory' => true, 'tl_class' => 'clr'),
    'sql' => "binary(16) NULL"
);

PaletteManipulator::create()
    ->addField('fileLocation', 'title_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_news_archive');