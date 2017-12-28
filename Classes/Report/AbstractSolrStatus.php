<?php
namespace ApacheSolrForTypo3\Solr\Report;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

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
    protected function getRenderedReport($templateFilename = '', $variables = [])
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
    private function getFluidStandaloneViewWithTemplate($templatePath = '')
    {
        $standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
        $standaloneView->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName($templatePath));

        return $standaloneView;
    }

    /**
     * Returns the status of an extension or (sub)system
     *
     * @return array An array of \TYPO3\CMS\Reports\Status objects
     */
    abstract public function getStatus();
}
