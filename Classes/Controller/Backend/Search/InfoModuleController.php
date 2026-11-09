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

namespace ApacheSolrForTypo3\Solr\Controller\Backend\Search;

use ApacheSolrForTypo3\Solr\Api;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Repository as ApacheSolrDocumentRepository;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchPhraseStatistics;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchTermClusterer;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchTermGrouper;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchTermNormalizer;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsFilterDto;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsLanguages;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Validator\Path;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;

/**
 * Info Module
 */
class InfoModuleController extends AbstractModuleController
{
    /**
     * Local date and time without a time zone, the format the date pickers of the statistics filter read and send.
     */
    public const STATISTICS_FILTER_DATE_FORMAT = 'Y-m-d\\TH:i:s';

    protected ApacheSolrDocumentRepository $apacheSolrDocumentRepository;

    /**
     * @inheritDoc
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();
        $this->apacheSolrDocumentRepository = GeneralUtility::makeInstance(ApacheSolrDocumentRepository::class);
    }

    /**
     * The date pickers of the statistics filter send local date and time
     * without a time zone, Extbase expects one by default.
     */
    public function initializeIndexAction(): void
    {
        if (!$this->arguments->hasArgument('statisticsFilter')) {
            return;
        }
        $configuration = $this->arguments->getArgument('statisticsFilter')->getPropertyMappingConfiguration();
        foreach (['startDate', 'endDate'] as $property) {
            $configuration->forProperty($property)->setTypeConverterOption(
                DateTimeConverter::class,
                DateTimeConverter::CONFIGURATION_DATE_FORMAT,
                self::STATISTICS_FILTER_DATE_FORMAT,
            );
        }
    }

    /**
     * Index action, shows an overview of the state of the Solr index
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     * @throws DBALException
     *
     * @noinspection PhpUnused
     */
    public function indexAction(
        ?StatisticsFilterDto $statisticsFilter = null,
        int $activeTabId = 0,
        string $operation = '',
    ): ResponseInterface {
        $this->moduleTemplate->assign('activeTabId', $activeTabId);

        if ($this->selectedSite === null) {
            $this->moduleTemplate->assign('can_not_proceed', true);
            return $this->moduleTemplate->renderResponse('Backend/Search/InfoModule/Index');
        }

        $this->collectConnectionInfos();
        $this->collectStatistics($statisticsFilter, $operation);
        $this->collectIndexFieldsInfo();
        $this->collectIndexInspectorInfo();

        return $this->moduleTemplate->renderResponse('Backend/Search/InfoModule/Index');
    }

    /**
     * Renders the details of Apache Solr documents
     *
     * @noinspection PhpUnused
     * @throws DBALException
     */
    public function documentsDetailsAction(string $type, int $uid, int $selectedPageUID, int $languageUid): ResponseInterface
    {
        $documents = $this->apacheSolrDocumentRepository->findByTypeAndPidAndUidAndLanguageId($type, $uid, $selectedPageUID, $languageUid);
        $this->moduleTemplate->assign('documents', $documents);
        return $this->moduleTemplate->renderResponse('Backend/Search/InfoModule/DocumentsDetails');
    }

    /**
     * Checks whether the configured Solr server can be reached and provides a
     * flash message according to the result of the check.
     */
    protected function collectConnectionInfos(): void
    {
        $connectedHosts = [];
        $missingHosts = [];
        $invalidPaths = [];

        /** @var Path $path */
        $path = GeneralUtility::makeInstance(Path::class);
        $connections = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);

        if (empty($connections)) {
            $this->moduleTemplate->assign('can_not_proceed', true);
            return;
        }

        $alreadyListedConnections = [];
        foreach ($connections as $connection) {
            $coreAdmin = $connection->getAdminService();
            $coreUrl = (string)$coreAdmin;
            if (in_array($coreUrl, $alreadyListedConnections)) {
                continue;
            }
            $alreadyListedConnections[] = $coreUrl;

            if ($coreAdmin->ping()) {
                $connectedHosts[] = $coreUrl;
            } else {
                $missingHosts[] = $coreUrl;
            }

            if (!$path->isValidSolrPath($coreAdmin->getCorePath())) {
                $invalidPaths[] = $coreAdmin->getCorePath();
            }
        }

        $this->moduleTemplate->assignMultiple([
            'site' => $this->selectedSite,
            'apiKey' => Api::getApiKey(),
            'connectedHosts' => $connectedHosts,
            'missingHosts' => $missingHosts,
            'invalidPaths' => $invalidPaths,
        ]);
    }

    /**
     * Returns the statistics
     *
     * @throws DBALException
     */
    protected function collectStatistics(?StatisticsFilterDto $statisticsFilterDto, string $operation): void
    {
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        $languages = GeneralUtility::makeInstance(StatisticsLanguages::class)
            ->forSite($this->selectedSite->getTypo3SiteObject(), $backendUser);
        $statisticsFilter = $this->getStatisticsFilter($statisticsFilterDto, $operation)
            ->setAllowedLanguageIds(array_keys($languages));

        /** @var StatisticsRepository $statisticsRepository */
        $statisticsRepository = GeneralUtility::makeInstance(StatisticsRepository::class);
        $phraseStatistics = new SearchPhraseStatistics(
            $statisticsRepository,
            new SearchTermGrouper(new SearchTermNormalizer()),
            new SearchTermClusterer(),
        );

        $this->moduleTemplate->assignMultiple([
            'top_search_phrases' => $phraseStatistics->getTopWithHits($statisticsFilter),
            'top_search_phrases_without_hits' => $phraseStatistics->getTopWithoutHits($statisticsFilter),
            'search_phrases_statistics' => $phraseStatistics->getAll($statisticsFilter),
            'statisticsLanguages' => $languages,
            'maximumTerms' => StatisticsRepository::MAXIMUM_TERMS,
            'limitOptions' => array_combine(StatisticsFilterDto::LIMIT_OPTIONS, StatisticsFilterDto::LIMIT_OPTIONS),
            // A language column only helps when several languages are listed together.
            'showLanguageColumn' => count($statisticsFilter->getLanguageIds()) > 1,
        ]);

        $labels = [];
        $data = [];
        $chartData = $statisticsRepository->getQueriesOverTime($statisticsFilter, 86400);

        foreach ($chartData as $bucket) {
            // @todo Replace deprecated strftime in php 8.1. Suppress warning for now
            $labels[] = @strftime('%x', $bucket['timestamp']);
            $data[] = (int)$bucket['numQueries'];
        }

        $this->moduleTemplate->assign('statisticsFilter', $statisticsFilter);
        $this->moduleTemplate->assign('queriesChartLabels', json_encode($labels));
        $this->moduleTemplate->assign('queriesChartData', json_encode($data));
        $this->moduleTemplate->assign('topHitsLimit', $statisticsFilter->getTopHitsLimit());
        $this->moduleTemplate->assign('noHitsLimit', $statisticsFilter->getNoHitsLimit());
    }

    /**
     * Gets Luke metadata for the currently selected core and provides a list
     * of that data.
     */
    protected function collectIndexFieldsInfo(): void
    {
        $indexFieldsInfoByCorePaths = [];

        $solrCoreConnections = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);
        foreach ($solrCoreConnections as $solrCoreConnection) {
            $coreAdmin = $solrCoreConnection->getAdminService();

            $indexFieldsInfo = [
                'corePath' => $coreAdmin->getCorePath(),
            ];
            if ($coreAdmin->ping()) {
                $lukeData = $coreAdmin->getLukeMetaData();
                $limitNote = '';

                if (isset($lukeData->index->numDocs) && $lukeData->index->numDocs > 20000) {
                    $limitNote = '<em>Too many terms</em>';
                } elseif (isset($lukeData->index->numDocs)) {
                    $limitNote = 'Nothing indexed';
                    // below limit, so we can get more data
                    // Note: we use 2 since 1 fails on Ubuntu Hardy.
                    $lukeData = $coreAdmin->getLukeMetaData(2);
                }

                $fields = $this->getFields($lukeData, $limitNote);
                $coreMetrics = $this->getCoreMetrics($lukeData, $fields);

                $indexFieldsInfo['noError'] = 'OK';
                $indexFieldsInfo['fields'] = $fields;
                $indexFieldsInfo['coreMetrics'] = $coreMetrics;
            } else {
                $indexFieldsInfo['noError'] = null;

                $this->addFlashMessage(
                    '',
                    'Unable to contact Apache Solr server: ' . $this->selectedSite->getLabel() . ' ' . $coreAdmin->getCorePath(),
                    ContextualFeedbackSeverity::ERROR,
                );
            }
            $indexFieldsInfoByCorePaths[$coreAdmin->getCorePath()] = $indexFieldsInfo;
        }
        $this->moduleTemplate->assign('indexFieldsInfoByCorePaths', $indexFieldsInfoByCorePaths);
    }

    /**
     * Retrieves the information for the index inspector.
     *
     * @throws DBALException
     */
    protected function collectIndexInspectorInfo(): void
    {
        $solrCoreConnections = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);
        $documentsByCoreAndType = [];
        $alreadyListedCores = [];
        foreach ($solrCoreConnections as $languageId => $solrCoreConnection) {
            $coreAdmin = $solrCoreConnection->getAdminService();

            // Do not list cores twice when multiple languages use the same core
            $url = (string)$coreAdmin;
            if (in_array($url, $alreadyListedCores)) {
                continue;
            }
            $alreadyListedCores[] = $url;

            $documents = $this->apacheSolrDocumentRepository->findByPageIdAndByLanguageId($this->selectedPageUID, $languageId);

            $documentsByType = [];
            foreach ($documents as $document) {
                $documentsByType[$document['type']][] = $document;
            }

            $documentsByCoreAndType[$languageId]['core'] = $coreAdmin;
            $documentsByCoreAndType[$languageId]['documents'] = $documentsByType;
        }

        $this->moduleTemplate->assignMultiple([
            'selectedPageUID' => $this->selectedPageUID,
            'indexInspectorDocumentsByLanguageAndType' => $documentsByCoreAndType,
        ]);
    }

    /**
     * Gets field metrics.
     *
     * @param ResponseAdapter $lukeData Luke index data
     * @param string $limitNote Note to display if there are too many documents in the index to show number of terms for a field
     *
     * @return array An array of field metrics
     */
    protected function getFields(ResponseAdapter $lukeData, string $limitNote): array
    {
        $rows = [];

        $fields = (array)$lukeData->fields;
        foreach ($fields as $name => $field) {
            $rows[$name] = [
                'name' => $name,
                'type' => $field->type,
                'docs' => $field->docs ?? 0,
                'terms' => $field->distinct ?? $limitNote,
            ];
        }
        ksort($rows);

        return $rows;
    }

    /**
     * Gets general core metrics.
     *
     * @param ResponseAdapter $lukeData Luke index data
     * @param array $fields Fields metrics
     *
     * @return array An array of core metrics
     */
    protected function getCoreMetrics(ResponseAdapter $lukeData, array $fields): array
    {
        return [
            'numberOfDocuments' => $lukeData->index->numDocs ?? 0,
            'numberOfDeletedDocuments' => $lukeData->index->deletedDocs ?? 0,
            'numberOfTerms' => $lukeData->index->numTerms ?? 0,
            'numberOfFields' => count($fields),
        ];
    }

    protected function getStatisticsFilter(?StatisticsFilterDto $statisticsFilterDto, string $operation): StatisticsFilterDto
    {
        $frameWorkConfiguration = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT,
            'solr',
        );
        $statisticsConfig = $frameWorkConfiguration['plugin.']['tx_solr.']['statistics.'] ?? [];

        if ($statisticsFilterDto === null || $operation === 'reset-filters') {
            $statisticsFilterDto = GeneralUtility::makeInstance(StatisticsFilterDto::class);
        }

        return $statisticsFilterDto->setFromTypoScriptConstants($statisticsConfig)
            ->setSiteRootPageId($this->selectedSite->getRootPageId());
    }
}
