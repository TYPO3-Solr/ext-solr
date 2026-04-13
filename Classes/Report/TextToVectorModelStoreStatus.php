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

namespace ApacheSolrForTypo3\Solr\Report;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about the TextToVectorModelStore
 */
class TextToVectorModelStoreStatus extends AbstractSolrStatus
{
    /**
     * Checks the status of the vector plugin and store
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     * @return array <int, Status>
     */
    public function getStatus(): array
    {
        $reports = [];
        /** @var ConnectionManager $connectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);

        $endpoints = [];
        foreach ($connectionManager->getAllConnections() as $connection) {
            $endpoints = $this->addEndpoint($endpoints, $connection, 'read');
            $endpoints = $this->addEndpoint($endpoints, $connection, 'write');
        }

        $configuredEndpoints = [];
        $unsupportedEndpoints = 0;
        $configuredModels = [];
        $missingModelConfigurations = 0;
        foreach ($endpoints as $endpoint => $adminService) {
            if (!property_exists(
                $adminService->getPluginsInformation()->plugins?->QUERYPARSER ?? '',
                'org.apache.solr.llm.textvectorisation.search.TextToVectorQParserPlugin',
            )) {
                $configuredEndpoints[] = [
                    'baseUrl' => $endpoint,
                    'status' => ContextualFeedbackSeverity::WARNING,
                ];
                $unsupportedEndpoints++;
                continue;
            }

            $configuredEndpoints[] = [
                'baseUrl' => $endpoint,
                'status' => ContextualFeedbackSeverity::OK,
            ];

            $models = $adminService->getVectorModelStore();
            if (!isset($models['llm'])) {
                $configuredModels[] = [
                    'baseUrl' => $endpoint,
                    'status' => ContextualFeedbackSeverity::WARNING,
                ];
                $missingModelConfigurations++;
            } else {
                $modelParams = (array)$models['llm']['params'];
                $configuredModels[] = [
                    'baseUrl' => $endpoint,
                    'status' => ContextualFeedbackSeverity::OK,
                    'class' => $models['llm']['class'] ?? '',
                    'modelBaseUrl' => $modelParams['baseUrl'] ?? '',
                    'modelName' => $modelParams['modelName'] ?? '',
                ];
            }
        }

        $reports[] = GeneralUtility::makeInstance(
            Status::class,
            'Apache Solr Text to Vector',
            '',
            $this->getRenderedReport(
                'TextToVectorModelStorePluginStatus.html',
                [
                    'endpoints' => $configuredEndpoints,
                    'unsupportedEndpoints' => $unsupportedEndpoints,
                ],
            ),
            ($unsupportedEndpoints ? ContextualFeedbackSeverity::WARNING : ContextualFeedbackSeverity::OK),
        );

        if ($configuredModels !== []) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                'Apache Solr Text to Vector: Configured Models',
                '',
                $this->getRenderedReport(
                    'TextToVectorModelStoreModelStatus.html',
                    [
                        'endpoints' => $configuredModels,
                        'missingModelConfigurations' => $missingModelConfigurations,
                    ],
                ),
                ($missingModelConfigurations ? ContextualFeedbackSeverity::WARNING : ContextualFeedbackSeverity::OK),
            );
        }

        return $reports;
    }

    /**
     * @param array<string, SolrAdminService> $endpoints
     * @return array<string, SolrAdminService>
     */
    protected function addEndpoint(array $endpoints, SolrConnection $connection, string $endpointType): array
    {
        $endpoint = $connection->getEndpoint($endpointType);
        $baseUri = $endpoint->getV1BaseUri();
        if (!isset($endpoints[$baseUri])) {
            $endpoints[$baseUri] = $connection->buildAdminService($endpointType);
        }

        return $endpoints;
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return 'LLL:EXT:solr/Resources/Private/Language/locallang_reports.xlf:status_solr_vector_support';
    }
}
