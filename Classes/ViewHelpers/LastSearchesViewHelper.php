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
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LastSearchesViewHelper
 *
 *
 * @noinspection PhpUnused
 */
class LastSearchesViewHelper extends AbstractSolrViewHelper
{
    protected $escapeChildren = false;

    protected $escapeOutput = false;

    /**
     * Renders last searches
     *
     * @throws DBALException
     */
    public function render()
    {
        /** @var ConfigurationManager $configurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $typoScriptConfiguration = $configurationManager->getTypoScriptConfiguration();
        $lastSearchesService = GeneralUtility::makeInstance(
            LastSearchesService::class,
            $typoScriptConfiguration,
        );
        $templateVariableContainer = $this->renderingContext->getVariableProvider();
        $templateVariableContainer->add('lastSearches', $lastSearchesService->getLastSearches());
        $output = $this->renderChildren();
        $templateVariableContainer->remove('lastSearches');
        return $output;
    }
}
