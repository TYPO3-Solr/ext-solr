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

namespace ApacheSolrForTypo3\Solr\ViewHelpers;

use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesService;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class LastSearchesViewHelper
 *
 * @author Rudy Gnodde <rudy.gnodde@beech.it>
 */
class LastSearchesViewHelper extends AbstractSolrViewHelper
{

    /**
     * @var bool
     */
    protected $escapeChildren = false;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return mixed|void
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        /** @var ConfigurationManager $configurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $typoScriptConfiguration = $configurationManager->getTypoScriptConfiguration();
        $lastSearchesService = GeneralUtility::makeInstance(
            LastSearchesService::class,
            $typoScriptConfiguration
        );
        $templateVariableContainer = $renderingContext->getVariableProvider();
        $templateVariableContainer->add('lastSearches', $lastSearchesService->getLastSearches());
        $output = $renderChildrenClosure();
        $templateVariableContainer->remove('lastSearches');
        return $output;
    }
}
