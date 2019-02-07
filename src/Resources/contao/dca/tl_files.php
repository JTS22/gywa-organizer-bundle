<?php

$GLOBALS['TL_DCA']['tl_files']['list']['global_operations']['adapt'] = array
(
    'label'               => &$GLOBALS['TL_LANG']['tl_files']['adapt'],
    'href'                => 'key=adapt',
    'class'               => 'header_adapt',
    'attributes'          => 'onclick="Backend.getScrollOffset()"'
);
array_push($GLOBALS['TL_DCA']['tl_files']['config']['onload_callback'], array('FileAdapter', 'adaptFilesToPageStructure'));
array_push($GLOBALS['TL_DCA']['tl_files']['fields']['name']['save_callback'], array('gywaorganizer.filemanager', 'checkFileName'));


class FileAdapter extends \Contao\System {

    public function adaptFilesToPageStructure() {
        $this->import('BackendUser', 'User');
        if (\Input::get("key") == "adapt") {
            if ($this->User->isAdmin) {
                $fileManager = System::getContainer()->get('gywaorganizer.filemanager');
                $fileManager->adaptFilesToPageStructure();
            } else {
                throw new \Contao\CoreBundle\Exception\AccessDeniedException("This action may only be used with administrator permissions!");
            }
        }

    }


}

?>