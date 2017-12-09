
<?php

$GLOBALS['TL_CTE']['gywa_ce_sitelist']['ce_sitelist'] = 'GyWa\FileOrganizerBundle\SiteList';

$GLOBALS['BE_MOD']['content']['gywa_category'] = array(
        'tables' => ['tl_category'],
        'table' => ['TableWizard', 'importTable'],
        'list' => ['ListWizard', 'importList']
);

?>
