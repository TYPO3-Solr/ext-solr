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
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Repository as ApacheSolrDocumentRepository;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Validator\Path;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Info Module
 */
class InfoModuleController extends AbstractModuleController
{
    /**
     * @var ApacheSolrDocumentRepository
     */
    protected ApacheSolrDocumentRepository $apacheSolrDocumentRepository;

    /**
     * @inheritDoc
     */
    protected function initializeAction()
    {
        parent::initializeAction();
        $this->apacheSolrDocumentRepository = GeneralUtility::makeInstance(ApacheSolrDocumentRepository::class);
    }

    /**
     * Index action, shows an overview of the state of the Solr index
     *
     * @return ResponseInterface
     */
    public function indexAction(): ResponseInterface
    {
        if ($this->selectedSite === null) {
            $this->view->assign('can_not_proceed', true);
            return $this->getModuleTemplateResponse();
        }

        $this->collectConnectionInfos();
        $this->collectStatistics();
        $this->collectIndexFieldsInfo();
        $this->collectIndexInspectorInfo();

        return $this->getModuleTemplateResponse();
    }

    /**
     * @param string $type
     * @param int $uid
     * @param int $pageId
     * @param int $languageUid
     * @return ResponseInterface
     */
    public function documentsDetailsAction(string $type, int $uid, int $pageId, int $languageUid): ResponseInterface
    {
        $documents = $this->apacheSolrDocumentRepository->findByTypeAndPidAndUidAndLanguageId($type, $uid, $pageId, $languageUid);
        $this->view->assign('documents', $documents);
        return $this->getModuleTemplateResponse();
    }

    /**
     * Checks whether the configured Solr server can be reached and provides a
     * flash message according to the result of the check.
     *
     * @return void
     */
    protected function collectConnectionInfos(): void
    {
        $connectedHosts = [];
        $missingHosts = [];
        $invalidPaths = [];

        /* @var Path $path */
        $path = GeneralUtility::makeInstance(Path::class);
        $connections = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);

        if (empty($connections)) {
            $this->view->assign('can_not_proceed', true);
            return;
        }

        foreach ($connections as $connection) {
            $coreAdmin = $connection->getAdminService();
            $coreUrl = (string)$coreAdmin;

            if ($coreAdmin->ping()) {
                $connectedHosts[] = $coreUrl;
            } else {
                $missingHosts[] = $coreUrl;
            }

            if (!$path->isValidSolrPath($coreAdmin->getCorePath())) {
                $invalidPaths[] = $coreAdmin->getCorePath();
            }
        }

        $this->view->assignMultiple([
            'site' => $this->selectedSite,
            'apiKey' => Api::getApiKey(),
            'connectedHosts' => $connectedHosts,
            'missingHosts' => $missingHosts,
            'invalidPaths' => $invalidPaths
        ]);
    }

    /**
     * Index action, shows an overview of the state of the Solr index
     *
     * @return void
     */
    protected function collectStatistics(): void
    {
        // TODO make time frame user adjustable, for now it's last 30 days

        $siteRootPageId = $this->selectedSite->getRootPageId();
        /* @var StatisticsRepository $statisticsRepository */
        $statisticsRepository = GeneralUtility::makeInstance(StatisticsRepository::class);

        // @TODO: Do we want Typoscript constants to restrict the results?
        $this->view->assign(
            'top_search_phrases',
            $statisticsRepository->getTopKeyWordsWithHits($siteRootPageId, 30, 5)
        );
        $this->view->assign(
            'top_search_phrases_without_hits',
            $statisticsRepository->getTopKeyWordsWithoutHits($siteRootPageId, 30, 5)
        );
        $this->view->assign(
            'search_phrases_statistics',
            $statisticsRepository->getSearchStatistics($siteRootPageId, 30, 100)
        );

        $labels = [];
        $data = [];
        $chartData = $statisticsRepository->getQueriesOverTime($siteRootPageId, 30, 86400);
        foreach ($chartData as $bucket) {
            $labels[] = strftime('%x', $bucket['timestamp']);
            $data[] = (int)$bucket['numQueries'];
        }

        $this->view->assign('queriesChartLabels', json_encode($labels));
        $this->view->assign('queriesChartData', json_encode($data));
    }

    /**
     * Gets Luke metadata for the currently selected core and provides a list
     * of that data.
     *
     * @return void
     */
    protected function collectIndexFieldsInfo(): void
    {
        $indexFieldsInfoByCorePaths = [];

        $solrCoreConnections = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);
        foreach ($solrCoreConnections as $solrCoreConnection) {
            $coreAdmin = $solrCoreConnection->getAdminService();

            $indexFieldsInfo = [
                'corePath' => $coreAdmin->getCorePath()
            ];
            if ($coreAdmin->ping()) {
                $lukeData = $coreAdmin->getLukeMetaData();

                /* @var Registry $registry */
                $registry = GeneralUtility::makeInstance(Registry::class);
                $limit = $registry->get('tx_solr', 'luke.limit', 20000);
                $limitNote = '';

                if (isset($lukeData->index->numDocs) && $lukeData->index->numDocs > $limit) {
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
                    FlashMessage::ERROR
                );
            }
            $indexFieldsInfoByCorePaths[$coreAdmin->getCorePath()] = $indexFieldsInfo;
        }
        $this->view->assign('indexFieldsInfoByCorePaths', $indexFieldsInfoByCorePaths);
    }

    /**
     * Retrieves the information for the index inspector.
     *
     * @return void
     */
    protected function collectIndexInspectorInfo(): void
    {
        $solrCoreConnections = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);
        $documentsByCoreAndType = [];
        foreach ($solrCoreConnections as $languageId => $solrCoreConnection) {
            $coreAdmin = $solrCoreConnection->getAdminService();
            $documents = $this->apacheSolrDocumentRepository->findByPageIdAndByLanguageId($this->selectedPageUID, $languageId);

            $documentsByType = [];
            foreach ($documents as $document) {
                $documentsByType[$document['type']][] = $document;
            }

            $documentsByCoreAndType[$languageId]['core'] = $coreAdmin;
            $documentsByCoreAndType[$languageId]['documents'] = $documentsByType;
        }

        $this->view->assignMultiple([
            'pageId' => $this->selectedPageUID,
            'indexInspectorDocumentsByLanguageAndType' => $documentsByCoreAndType
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
                'terms' => $field->distinct ?? $limitNote
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
            'numberOfFields' => count($fields)
        ];
    }
}
