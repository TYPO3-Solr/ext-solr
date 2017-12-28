<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Statistics;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetProcessor;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\HtmlContentExtractor;
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
     * @param StatisticsRepository $statisticsRepository
     */
    public function __construct(StatisticsRepository $statisticsRepository = null)
    {
        $this->statisticsRepository = isset($statisticsRepository) ? $statisticsRepository : GeneralUtility::makeInstance(StatisticsRepository::class);
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
        $ipMaskLength = $configuration->getStatisticsAnonymizeIP();

        $TSFE = $this->getTSFE();
        $statisticData = [
            'pid' => $TSFE->id,
            'root_pid' => $TSFE->tmpl->rootLine[0]['uid'],
            'tstamp' => $this->getTime(),
            'language' => $TSFE->sys_language_uid,
            'num_found' => isset($response->response->numFound) ? (int)$response->response->numFound : 0,
            'suggestions_shown' => is_object($response->spellcheck->suggestions) ? (int)get_object_vars($response->spellcheck->suggestions) : 0,
            'time_total' => isset($response->debug->timing->time) ? $response->debug->timing->time : 0,
            'time_preparation' => isset($response->debug->timing->prepare->time) ? $response->debug->timing->prepare->time : 0,
            'time_processing' => isset($response->debug->timing->process->time) ? $response->debug->timing->process->time : 0,
            'feuser_id' => (int)$TSFE->fe_user->user['uid'],
            'cookie' => $TSFE->fe_user->id,
            'ip' => $this->applyIpMask($this->getUserIp(), $ipMaskLength),
            'page' => (int)$page,
            'keywords' => $keywords,
            'filters' => serialize($filters),
            'sorting' => $sorting,
            'parameters' => serialize($response->responseHeader->params)
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
        $keywords = $query->getKeywords();
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
    protected function sanitizeString($string)
    {
        // clean content
        $string = HtmlContentExtractor::cleanContent($string);
        $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
        $string = filter_var(strip_tags($string), FILTER_SANITIZE_STRING); // after entity decoding we might have tags again
        $string = trim($string);

        return $string;
    }

    /**
     * Internal function to mask portions of the visitor IP address
     *
     * @param string $ip IP address in network address format
     * @param int $maskLength Number of octets to reset
     * @return string
     */
    protected function applyIpMask($ip, $maskLength)
    {
        // IPv4 or mapped IPv4 in IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $i = strlen($ip);
            if ($maskLength > $i) {
                $maskLength = $i;
            }

            while ($maskLength-- > 0) {
                $ip[--$i] = chr(0);
            }
        } else {
            $masks = [
                'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff',
                'ffff:ffff:ffff:ffff::',
                'ffff:ffff:ffff:0000::',
                'ffff:ff00:0000:0000::'
            ];
            return $ip & pack('a16', inet_pton($masks[$maskLength]));
        }

        return $ip;
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
        return $GLOBALS['EXEC_TIME'];
    }
}
