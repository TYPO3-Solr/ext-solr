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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Llm;

use Throwable;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class LlmQueryEnhancerService
{
    public const DEFAULT_CONFIGURATION_IDENTIFIER = 'solr-search-query-enhancer';

    private const CONFIGURATION_REPOSITORY_CLASS = 'Netresearch\\NrLlm\\Domain\\Repository\\LlmConfigurationRepository';
    private const LLM_SERVICE_MANAGER_INTERFACE = 'Netresearch\\NrLlm\\Service\\LlmServiceManagerInterface';

    private const TABLE_CONFIGURATION = 'tx_nrllm_configuration';
    private const TABLE_MODEL = 'tx_nrllm_model';
    private const TABLE_PROVIDER = 'tx_nrllm_provider';
    private const TABLE_USAGE = 'tx_nrllm_service_usage';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly FrontendInterface $cache,
    ) {}

    public function isNrLlmAvailable(): bool
    {
        return class_exists(self::CONFIGURATION_REPOSITORY_CLASS)
            && interface_exists(self::LLM_SERVICE_MANAGER_INTERFACE);
    }

    public function enhanceQuery(
        string $rawQuery,
        string $configurationIdentifier = self::DEFAULT_CONFIGURATION_IDENTIFIER,
        int $languageId = 0,
        int $cacheLifetime = 86400,
    ): LlmQueryEnhancementResult {
        $rawQuery = trim($rawQuery);
        $configurationIdentifier = trim($configurationIdentifier) ?: self::DEFAULT_CONFIGURATION_IDENTIFIER;

        if (!$this->canEnhanceQuery($rawQuery)) {
            return new LlmQueryEnhancementResult($rawQuery, $rawQuery, false, $configurationIdentifier, 'skipped');
        }

        if (!$this->isNrLlmAvailable()) {
            return new LlmQueryEnhancementResult(
                $rawQuery,
                $rawQuery,
                false,
                $configurationIdentifier,
                'unavailable',
                'EXT:nr_llm is not available.',
            );
        }

        $cacheKey = $this->buildCacheKey($configurationIdentifier, $rawQuery, $languageId);
        $cachedQuery = $cacheLifetime > 0 ? $this->cache->get($cacheKey) : false;
        if (is_string($cachedQuery) && $cachedQuery !== '') {
            return new LlmQueryEnhancementResult(
                $rawQuery,
                $cachedQuery,
                $cachedQuery !== $rawQuery,
                $configurationIdentifier,
                'ok',
                null,
                null,
                null,
                true,
            );
        }

        try {
            $configuration = $this->findConfigurationEntity($configurationIdentifier);
            if ($configuration === null) {
                return new LlmQueryEnhancementResult(
                    $rawQuery,
                    $rawQuery,
                    false,
                    $configurationIdentifier,
                    'missingConfiguration',
                    'No active nr_llm configuration was found.',
                );
            }

            if (method_exists($configuration, 'isActive') && !$configuration->isActive()) {
                return new LlmQueryEnhancementResult(
                    $rawQuery,
                    $rawQuery,
                    false,
                    $configurationIdentifier,
                    'inactiveConfiguration',
                    'The nr_llm configuration is inactive.',
                );
            }

            $serviceManager = GeneralUtility::makeInstance(self::LLM_SERVICE_MANAGER_INTERFACE);
            $response = $serviceManager->chatWithConfiguration($this->buildMessages($rawQuery), $configuration);
            $enhancedQuery = $this->sanitizeEnhancedQuery((string)$response->content, $rawQuery);

            if ($cacheLifetime > 0) {
                $this->cache->set($cacheKey, $enhancedQuery, ['tx_solr', 'tx_solr_llm_query_enhancer'], $cacheLifetime);
            }

            return new LlmQueryEnhancementResult(
                $rawQuery,
                $enhancedQuery,
                $enhancedQuery !== $rawQuery,
                $configurationIdentifier,
                'ok',
                null,
                (string)($response->provider ?? ''),
                (string)($response->model ?? ''),
            );
        } catch (Throwable $exception) {
            return new LlmQueryEnhancementResult(
                $rawQuery,
                $rawQuery,
                false,
                $configurationIdentifier,
                'failed',
                $exception->getMessage(),
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableConfigurations(): array
    {
        if (!$this->isNrLlmAvailable()) {
            return [];
        }

        try {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_CONFIGURATION);
            $rows = $queryBuilder
                ->selectLiteral(
                    'c.uid',
                    'c.identifier',
                    'c.name',
                    'c.description',
                    'c.is_active',
                    'c.temperature',
                    'c.max_tokens',
                    'm.name AS model_name',
                    'm.model_id',
                    'm.is_active AS model_is_active',
                    'p.name AS provider_name',
                    'p.identifier AS provider_identifier',
                    'p.adapter_type',
                    'p.is_active AS provider_is_active',
                )
                ->from(self::TABLE_CONFIGURATION, 'c')
                ->leftJoin('c', self::TABLE_MODEL, 'm', 'm.uid = c.model_uid AND m.deleted = 0')
                ->leftJoin('m', self::TABLE_PROVIDER, 'p', 'p.uid = m.provider_uid AND p.deleted = 0')
                ->where($queryBuilder->expr()->eq('c.deleted', $queryBuilder->createNamedParameter(0)))
                ->orderBy('c.sorting')
                ->addOrderBy('c.name')
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Throwable) {
            return [];
        }

        return array_map(
            static function (array $row): array {
                return [
                    'uid' => (int)($row['uid'] ?? 0),
                    'identifier' => (string)($row['identifier'] ?? ''),
                    'name' => (string)($row['name'] ?? ''),
                    'description' => (string)($row['description'] ?? ''),
                    'isActive' => (bool)($row['is_active'] ?? false),
                    'temperature' => (float)($row['temperature'] ?? 0.0),
                    'maxTokens' => (int)($row['max_tokens'] ?? 0),
                    'modelName' => (string)($row['model_name'] ?? ''),
                    'modelId' => (string)($row['model_id'] ?? ''),
                    'modelIsActive' => (bool)($row['model_is_active'] ?? false),
                    'providerName' => (string)($row['provider_name'] ?? ''),
                    'providerIdentifier' => (string)($row['provider_identifier'] ?? ''),
                    'adapterType' => (string)($row['adapter_type'] ?? ''),
                    'providerIsActive' => (bool)($row['provider_is_active'] ?? false),
                ];
            },
            $rows,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $configurations
     * @return array<string, mixed>|null
     */
    public function findConfigurationOverview(array $configurations, string $configurationIdentifier): ?array
    {
        foreach ($configurations as $configuration) {
            if (($configuration['identifier'] ?? '') === $configurationIdentifier) {
                return $configuration;
            }
        }
        return null;
    }

    /**
     * @return array{requests: int, tokens: int, cost: float, latest: int}
     */
    public function getUsageStats(int $configurationUid): array
    {
        if ($configurationUid <= 0) {
            return ['requests' => 0, 'tokens' => 0, 'cost' => 0.0, 'latest' => 0];
        }

        try {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_USAGE);
            $row = $queryBuilder
                ->selectLiteral(
                    'SUM(request_count) AS requests',
                    'SUM(tokens_used) AS tokens',
                    'SUM(estimated_cost) AS cost',
                    'MAX(tstamp) AS latest',
                )
                ->from(self::TABLE_USAGE)
                ->where($queryBuilder->expr()->eq('configuration_uid', $queryBuilder->createNamedParameter($configurationUid)))
                ->executeQuery()
                ->fetchAssociative();
        } catch (Throwable) {
            return ['requests' => 0, 'tokens' => 0, 'cost' => 0.0, 'latest' => 0];
        }

        if (!is_array($row)) {
            return ['requests' => 0, 'tokens' => 0, 'cost' => 0.0, 'latest' => 0];
        }

        return [
            'requests' => (int)($row['requests'] ?? 0),
            'tokens' => (int)($row['tokens'] ?? 0),
            'cost' => (float)($row['cost'] ?? 0.0),
            'latest' => (int)($row['latest'] ?? 0),
        ];
    }

    /**
     * @return array{status: string, success: bool, message: string, models: array<int|string, mixed>, messageKey?: string}
     */
    public function testConfigurationConnection(string $configurationIdentifier): array
    {
        $configurationIdentifier = trim($configurationIdentifier) ?: self::DEFAULT_CONFIGURATION_IDENTIFIER;
        if (!$this->isNrLlmAvailable()) {
            return [
                'status' => 'error',
                'success' => false,
                'message' => '',
                'messageKey' => 'testResult.nrLlmUnavailable',
                'models' => [],
            ];
        }

        try {
            $configuration = $this->findConfigurationEntity($configurationIdentifier);
            if ($configuration === null) {
                return [
                    'status' => 'error',
                    'success' => false,
                    'message' => '',
                    'messageKey' => 'testResult.missingConfiguration',
                    'models' => [],
                ];
            }

            $serviceManager = GeneralUtility::makeInstance(self::LLM_SERVICE_MANAGER_INTERFACE);
            if (!method_exists($serviceManager, 'getAdapterFromConfiguration')) {
                return [
                    'status' => 'warning',
                    'success' => false,
                    'message' => '',
                    'messageKey' => 'testResult.unsupportedTest',
                    'models' => [],
                ];
            }

            $adapter = $serviceManager->getAdapterFromConfiguration($configuration);
            $result = $adapter->testConnection();

            return [
                'status' => !empty($result['success']) ? 'ok' : 'error',
                'success' => (bool)($result['success'] ?? false),
                'message' => (string)($result['message'] ?? ''),
                'models' => is_array($result['models'] ?? null) ? $result['models'] : [],
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'success' => false,
                'message' => $exception->getMessage(),
                'models' => [],
            ];
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildMessages(string $rawQuery): array
    {
        return [
            [
                'role' => 'system',
                'content' => 'You improve short TYPO3 website search queries for Apache Solr. Return only JSON with the shape {"query":"..."}. Keep names, product codes, quoted phrases and language. Do not return filters, field names, Solr operators, explanations or markdown. Use at most twelve words.',
            ],
            [
                'role' => 'user',
                'content' => json_encode(['query' => $rawQuery], JSON_THROW_ON_ERROR),
            ],
        ];
    }

    private function canEnhanceQuery(string $rawQuery): bool
    {
        if ($rawQuery === '' || $rawQuery === '*' || $rawQuery === '*:*') {
            return false;
        }

        return mb_strlen($rawQuery) >= 2;
    }

    private function sanitizeEnhancedQuery(string $response, string $fallbackQuery): string
    {
        $query = trim($response);
        $query = preg_replace('/^```(?:json)?|```$/i', '', $query) ?? $query;
        $query = trim($query);

        $decoded = json_decode($query, true);
        if (is_array($decoded) && is_string($decoded['query'] ?? null)) {
            $query = trim($decoded['query']);
        }

        $query = preg_replace('/^\s*(query|suchbegriff)\s*:\s*/i', '', $query) ?? $query;
        $query = trim($query, " \t\n\r\0\x0B\"'`");
        $query = preg_replace('/[\r\n\t]+/', ' ', $query) ?? $query;
        $query = preg_replace('/\s{2,}/', ' ', $query) ?? $query;

        if ($query === '' || mb_strlen($query) > 200 || str_contains($query, '{!')) {
            return $fallbackQuery;
        }

        return $query;
    }

    private function buildCacheKey(string $configurationIdentifier, string $rawQuery, int $languageId): string
    {
        return 'llm_query_enhancer_' . sha1($configurationIdentifier . '|' . $languageId . '|' . $rawQuery);
    }

    private function findConfigurationEntity(string $configurationIdentifier): ?object
    {
        if (!class_exists(self::CONFIGURATION_REPOSITORY_CLASS)) {
            return null;
        }

        $repository = GeneralUtility::makeInstance(self::CONFIGURATION_REPOSITORY_CLASS);
        if (!method_exists($repository, 'findOneByIdentifier')) {
            return null;
        }

        $configuration = $repository->findOneByIdentifier($configurationIdentifier);
        return is_object($configuration) ? $configuration : null;
    }
}
