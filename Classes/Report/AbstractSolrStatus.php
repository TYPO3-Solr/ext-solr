<?php

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

use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\StatusProviderInterface;

/**
 * Provides shared functionality for all Solr reports.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractSolrStatus implements StatusProviderInterface
{
    /**
     * Assigns variables to the fluid StandaloneView and renders the view.
     *
     * @param string $templateFilename
     * @param array $variables
     * @return string
     */
    protected function getRenderedReport(string $templateFilename = '', array $variables = []): string
    {
        $templatePath = 'EXT:solr/Resources/Private/Templates/Backend/Reports/' . $templateFilename;
        $standaloneView = $this->getFluidStandaloneViewWithTemplate($templatePath);
        $standaloneView->assignMultiple($variables);

        return $standaloneView->render();
    }

    /**
     * Initializes a StandaloneView with a template and returns it.
     *
     * @param string $templatePath
     * @return StandaloneView
     */
    private function getFluidStandaloneViewWithTemplate(string $templatePath = ''): StandaloneView
    {
        $standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
        $standaloneView->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName($templatePath));

        return $standaloneView;
    }
}
