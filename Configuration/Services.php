<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use ApacheSolrForTypo3\Solr\Updates\SolrSearchCTypeMigration;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate;

return static function (ContainerConfigurator $configurator): void {
    // EXT:install is optional; register the upgrade wizard only when it is available.
    if (!class_exists(AbstractListTypeToCTypeUpdate::class)) {
        return;
    }

    $configurator->services()
        ->set(SolrSearchCTypeMigration::class)
        ->autowire()
        ->autoconfigure()
        ->share(false);
};
