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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Statistics;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetProcessor;
use ApacheSolrForTypo3\Solr\HtmlContentExtractor;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Writes statistics after searches have been conducted.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Dimitri Ebert <dimitri.ebert@dkd.de>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class StatisticsWriterProcessor implements SearchResultSetProcessor
{
    /**
     * @var StatisticsRepository
     */
    protected $statisticsRepository;

    /**
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @param StatisticsRepository $statisticsRepository
     * @param SiteRepository $siteRepository
     */
    public function __construct(StatisticsRepository $statisticsRepository = null, SiteRepository $siteRepository = null)
    {
        $this->statisticsRepository = $statisticsRepository ?? GeneralUtility::makeInstance(StatisticsRepository::class);
        $this->siteRepository = $siteRepository ?? GeneralUtility::makeInstance(SiteRepository::class);
    }

    /**
     * @param SearchResultSet $resultSet
     * @return SearchResultSet
     */
    public function process(SearchResultSet $resultSet) {
        $searchRequest = $resultSet->getUsedSearchRequest();
        $response = $resultSet->getResponse();
        $configuration = $searchRequest->getContextTypoScriptConfiguration();
        $keywords = $this->getProcessedKeywords($resultSet->getUsedQuery(), $configuration->getSearchFrequentSearchesUseLowercaseKeywords());

        if (empty($keywords)) {
            // do not track empty queries
            return $resultSet;
        }

        $filters = $searchRequest->getActiveFacets();
        $sorting = $this->sanitizeString($searchRequest->getSorting());
        $page = (int)$searchRequest->getPage();
        $ipMaskLength = (int)$configuration->getStatisticsAnonymizeIP();

        $TSFE = $this->getTSFE();
        $root_pid = $this->siteRepository->getSiteByPageId($TSFE->id)->getRootPageId();
        $statisticData = [
            'pid' => $TSFE->id,
            'root_pid' => $root_pid,
            'tstamp' => $this->getTime(),
            'language' => Util::getLanguageUid(),
            // @extensionScannerIgnoreLine
            'num_found' => (int)$resultSet->getAllResultCount(),
            'suggestions_shown' => is_object($response->spellcheck->suggestions ?? null) ? (int)get_object_vars($response->spellcheck->suggestions) : 0,
            // @extensionScannerIgnoreLine
            'time_total' => $response->debug->timing->time ?? 0,
            // @extensionScannerIgnoreLine
            'time_preparation' => $response->debug->timing->prepare->time ?? 0,
            // @extensionScannerIgnoreLine
            'time_processing' => $response->debug->timing->process->time ?? 0,
            'feuser_id' => isset($TSFE->fe_user->user) ? (int)$TSFE->fe_user->user['uid'] ?? 0 : 0,
            'cookie' => $TSFE->fe_user->id ?? '',
            'ip' => $this->applyIpMask((string)$this->getUserIp(), $ipMaskLength),
            'page' => (int)$page,
            'keywords' => $keywords,
            'filters' => serialize($filters),
            'sorting' => $sorting,
            'parameters' => isset($response->responseHeader->params) ? serialize($response->responseHeader->params) : ''
        ];

        $this->statisticsRepository->saveStatisticsRecord($statisticData);

        return $resultSet;
    }

    /**
     * @param Query $query
     * @param boolean $lowerCaseQuery
     * @return string
     */
    protected function getProcessedKeywords(Query $query, $lowerCaseQuery = false)
    {
        $keywords = $query->getQuery();
        $keywords = $this->sanitizeString($keywords);
        if ($lowerCaseQuery) {
            $keywords = mb_strtolower($keywords);
        }

        return $keywords;
    }

    /**
     * Sanitizes a string
     *
     * @param $string String to sanitize
     * @return string Sanitized string
     */
    protected function sanitizeString($string): string
    {
        // clean content
        $string = HtmlContentExtractor::cleanContent($string);
        $string = htmlspecialchars(strip_tags($string), ENT_QUOTES, 'UTF-8'); // after entity decoding we might have tags again
        return trim($string);
    }

    /**
     * Internal function to mask portions of the visitor IP address
     *
     * @param string $ip IP address in network address format
     * @param int $maskLength Number of octets to reset
     * @return string
     */
    protected function applyIpMask(string $ip, int $maskLength): string
    {
        if (empty($ip) || $maskLength === 0) {
            return $ip;
        }

        // IPv4 or mapped IPv4 in IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->applyIpV4Mask($ip, $maskLength);
        }

        return $this->applyIpV6Mask($ip, $maskLength);
    }

    /**
     * Apply a mask filter on the ip v4 address.
     *
     * @param string $ip
     * @param int $maskLength
     * @return string
     */
    protected function applyIpV4Mask($ip, $maskLength)
    {
        $i = strlen($ip);
        if ($maskLength > $i) {
            $maskLength = $i;
        }

        while ($maskLength-- > 0) {
            $ip[--$i] = chr(0);
        }
        return (string)$ip;
    }

    /**
     * Apply a mask filter on the ip v6 address.
     *
     * @param string $ip
     * @param int $maskLength
     * @return string
     */
    protected function applyIpV6Mask($ip, $maskLength):string
    {
        $masks = ['ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff', 'ffff:ffff:ffff:ffff::', 'ffff:ffff:ffff:0000::', 'ffff:ff00:0000:0000::'];
        $packedAddress = inet_pton($masks[$maskLength]);
        $binaryString = pack('a16', $packedAddress);
        return (string)($ip & $binaryString);
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTSFE()
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * @return string
     */
    protected function getUserIp()
    {
        return GeneralUtility::getIndpEnv('REMOTE_ADDR');
    }

    /**
     * @return mixed
     */
    protected function getTime()
    {
        return GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
    }
}
