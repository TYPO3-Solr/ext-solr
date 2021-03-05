<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Widget\Controller;

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

use ApacheSolrForTypo3\Solr\Domain\Search\FrequentSearches\FrequentSearchesService;
use ApacheSolrForTypo3\Solr\Widget\AbstractWidgetController;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FrequentlySearchedController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class FrequentlySearchedController extends AbstractWidgetController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Initializes the cache for this command.
     *
     * @return FrontendInterface|null
     */
    protected function getInitializedCache(): ?FrontendInterface
    {
        $cacheIdentifier = 'tx_solr';
        /* @var FrontendInterface $cacheInstance */
        try {
            $cacheInstance = GeneralUtility::makeInstance(CacheManager::class)->getCache($cacheIdentifier);
        } catch (NoSuchCacheException $exception) {
            $this->logger->error('Getting cache failed: ' . $exception->getMessage());
            return null;
        }

        return $cacheInstance;
    }

    /**
     * Last searches
     */
    public function indexAction()
    {
        $tsfe = $GLOBALS['TSFE'];
        $cache = $this->getInitializedCache();
        $configuration = $this->controllerContext->getTypoScriptConfiguration();

        /* @var FrequentSearchesService $frequentSearchesService */
        $frequentSearchesService = GeneralUtility::makeInstance(
            FrequentSearchesService::class,
            /** @scrutinizer ignore-type */ $configuration,
            /** @scrutinizer ignore-type */ $cache,
            /** @scrutinizer ignore-type */ $tsfe
        );

        $frequentSearches = $frequentSearchesService->getFrequentSearchTerms();
        $minimumSize = $configuration->getSearchFrequentSearchesMinSize();
        $maximumSize = $configuration->getSearchFrequentSearchesMaxSize();

        $this->view->assign(
            'contentArguments',
            [
                'frequentSearches' => $this->enrichFrequentSearchesInfo($frequentSearches, $minimumSize, $maximumSize)
            ]
        );
    }

    /**
     * Enrich the frequentSearches
     *
     * @param array Frequent search terms as array with terms as keys and hits as the value
     * @param int $minimumSize
     * @param int $maximumSize
     * @return array An array with content for the frequent terms markers
     */
    protected function enrichFrequentSearchesInfo(array $frequentSearchTerms, int $minimumSize, int $maximumSize): array
    {
        $frequentSearches = [];
        if (count($frequentSearchTerms)) {
            $maximumHits = max(array_values($frequentSearchTerms));
            $minimumHits = min(array_values($frequentSearchTerms));
            $spread = $maximumHits - $minimumHits;
            $step = ($spread == 0) ? 1 : ($maximumSize - $minimumSize) / $spread;

            foreach ($frequentSearchTerms as $term => $hits) {
                $size = round($minimumSize + (($hits - $minimumHits) * $step));
                $frequentSearches[] = [
                    'q' => htmlspecialchars_decode($term),
                    'hits' => $hits,
                    'style' => 'font-size: ' . $size . 'px', 'class' => 'tx-solr-frequent-term-' . $size,
                    'size' => $size
                ];
            }
        }

        return $frequentSearches;
    }
}
