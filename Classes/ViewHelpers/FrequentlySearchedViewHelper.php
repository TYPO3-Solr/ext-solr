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

namespace ApacheSolrForTypo3\Solr\ViewHelpers;

use ApacheSolrForTypo3\Solr\Domain\Search\FrequentSearches\FrequentSearchesService;
use ApacheSolrForTypo3\Solr\Exception as SolrException;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException as AspectNotFoundExceptionAlias;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LastSearchesViewHelper
 *
 *
 * @noinspection PhpUnused
 */
class FrequentlySearchedViewHelper extends AbstractSolrViewHelper
{
    /**
     * @inheritdoc
     */
    protected $escapeChildren = false;

    /**
     * @inheritdoc
     */
    protected $escapeOutput = false;

    /**
     * Renders frequently searches component.
     *
     * @throws AspectNotFoundExceptionAlias
     * @throws DBALException
     * @throws SolrException
     */
    public function render()
    {
        $cache = self::getInitializedCache();
        /** @var ConfigurationManager $configurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $typoScriptConfiguration = $configurationManager->getTypoScriptConfiguration();
        /** @var FrequentSearchesService $frequentSearchesService */
        $frequentSearchesService = GeneralUtility::makeInstance(
            FrequentSearchesService::class,
            $typoScriptConfiguration,
            $cache,
        );

        $request = $this->renderingContext->getAttribute(ServerRequestInterface::class);
        $frequentSearches = $frequentSearchesService->getFrequentSearchTerms($request);
        $minimumSize = $typoScriptConfiguration->getSearchFrequentSearchesMinSize();
        $maximumSize = $typoScriptConfiguration->getSearchFrequentSearchesMaxSize();

        $templateVariableContainer = $this->renderingContext->getVariableProvider();
        $templateVariableContainer->add('frequentSearches', self::enrichFrequentSearchesInfo($frequentSearches, $minimumSize, $maximumSize));
        $output = $this->renderChildren();
        $templateVariableContainer->remove('frequentSearches');
        return $output;
    }

    /**
     * Initializes the cache for this command.
     */
    protected static function getInitializedCache(): ?FrontendInterface
    {
        $cacheIdentifier = 'tx_solr';
        try {
            /** @var CacheManager $cacheManager */
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            $cacheInstance = $cacheManager->getCache($cacheIdentifier);
        } catch (NoSuchCacheException) {
            return null;
        }

        return $cacheInstance;
    }

    /**
     * Enrich the frequentSearches
     *
     * @param array $frequentSearchTerms Frequent search terms as array with terms as keys and hits as the value
     * @return array An array with content for the frequent terms markers
     */
    protected static function enrichFrequentSearchesInfo(array $frequentSearchTerms, int $minimumSize, int $maximumSize): array
    {
        $frequentSearches = [];
        if (count($frequentSearchTerms)) {
            $maximumHits = max(array_values($frequentSearchTerms));
            $minimumHits = min(array_values($frequentSearchTerms));
            $spread = $maximumHits - $minimumHits;
            $step = ($spread === 0) ? 1 : ($maximumSize - $minimumSize) / $spread;

            foreach ($frequentSearchTerms as $term => $hits) {
                $size = round($minimumSize + (($hits - $minimumHits) * $step));
                $frequentSearches[] = [
                    'q' => htmlspecialchars_decode((string)$term),
                    'hits' => $hits,
                    'style' => 'font-size: ' . $size . 'px', 'class' => 'tx-solr-frequent-term-' . $size,
                    'size' => $size,
                ];
            }
        }

        return $frequentSearches;
    }
}
