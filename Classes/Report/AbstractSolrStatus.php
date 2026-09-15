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

namespace ApacheSolrForTypo3\Solr\Report;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Reports\StatusProviderInterface;

/**
 * Provides shared functionality for all Solr reports.
 */
abstract class AbstractSolrStatus implements StatusProviderInterface
{
    public function __construct(
        protected readonly ViewFactoryInterface $viewFactory,
    ) {}

    /**
     * Assigns variables to the fluid StandaloneView and renders the view.
     */
    protected function getRenderedReport(string $templateFilename = '', array $variables = []): string
    {
        $templatePath = 'EXT:solr/Resources/Private/Templates/Backend/Reports/' . $templateFilename;
        $view = $this->viewFactory->create(new ViewFactoryData(
            templatePathAndFilename: GeneralUtility::getFileAbsFileName($templatePath),
        ));
        $view->assignMultiple($variables);

        return $view->render();
    }
}
