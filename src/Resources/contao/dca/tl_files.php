<?php

$GLOBALS['TL_DCA']['tl_files']['list']['global_operations']['adapt'] = array
(
    'label'               => &$GLOBALS['TL_LANG']['tl_files']['adapt'],
    'href'                => 'key=adapt',
    'class'               => 'header_adapt',
    'attributes'          => 'onclick="Backend.getScrollOffset()"'
);
array_push($GLOBALS['TL_DCA']['tl_files']['config']['onload_callback'], array('gywaorganizer.filemanager', 'adaptFilesToPageStructure'));
array_push($GLOBALS['TL_DCA']['tl_files']['fields']['name']['save_callback'], array('gywaorganizer.filemanager', 'checkFileName'));


?>