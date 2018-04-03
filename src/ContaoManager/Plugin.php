<?php

namespace GyWa\OrganizerBundle\ContaoManager;

use Contao\NewsBundle\ContaoNewsBundle;
use GyWa\OrganizerBundle\GyWaOrganizerBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerBundle\ContaoManagerBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(GyWaOrganizerBundle::class)
            ->setLoadAfter(
                [
                    ContaoCoreBundle::class,
                    ContaoNewsBundle::class,
                    ContaoManagerBundle::class,
                ]
            ),
        ];
    }
}

?>
