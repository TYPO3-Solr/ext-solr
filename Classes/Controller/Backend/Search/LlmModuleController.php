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

namespace ApacheSolrForTypo3\Solr\Controller\Backend\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\Llm\LlmQueryEnhancerService;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class LlmModuleController extends AbstractModuleController
{
    private const LANGUAGE_DOMAIN = 'solr.modules.llm';

    private LlmQueryEnhancerService $llmQueryEnhancerService;

    protected function initializeAction(): void
    {
        parent::initializeAction();
        $this->llmQueryEnhancerService = GeneralUtility::makeInstance(LlmQueryEnhancerService::class);
    }

    public function indexAction(
        string $configurationIdentifier = '',
        string $operation = '',
    ): ResponseInterface {
        $runtimeConfiguration = $this->getRuntimeConfiguration();
        $configurationIdentifier = trim($configurationIdentifier)
            ?: $runtimeConfiguration['configurationIdentifier'];

        $configurations = $this->llmQueryEnhancerService->getAvailableConfigurations();
        $selectedConfiguration = $this->llmQueryEnhancerService->findConfigurationOverview(
            $configurations,
            $configurationIdentifier,
        );
        $usage = $selectedConfiguration !== null
            ? $this->llmQueryEnhancerService->getUsageStats((int)$selectedConfiguration['uid'])
            : ['requests' => 0, 'tokens' => 0, 'cost' => 0.0, 'latest' => 0];
        $connectionTest = null;

        if ($operation === 'testConnection') {
            $connectionTest = $this->llmQueryEnhancerService->testConfigurationConnection($configurationIdentifier);
            $connectionTest['message'] = $this->translateConnectionTestMessage($connectionTest);
            $this->addFlashMessage(
                $connectionTest['message'],
                $this->translate('testResult.title'),
                $connectionTest['success'] ? ContextualFeedbackSeverity::OK : ContextualFeedbackSeverity::ERROR,
            );
        }

        $this->moduleTemplate->assignMultiple([
            'nrLlmAvailable' => $this->llmQueryEnhancerService->isNrLlmAvailable(),
            'configurations' => $configurations,
            'configurationOptions' => $this->buildConfigurationOptions($configurations),
            'selectedConfiguration' => $selectedConfiguration,
            'selectedConfigurationIdentifier' => $configurationIdentifier,
            'runtimeConfiguration' => $runtimeConfiguration,
            'usage' => $usage,
            'connectionTest' => $connectionTest,
            'nrLlmConfigurationModuleUri' => $this->getBackendModuleUri('nrllm_configurations'),
            'nrLlmWizardModuleUri' => $this->getBackendModuleUri('nrllm_wizard'),
        ]);

        return $this->moduleTemplate->renderResponse('Backend/Search/LlmModule/Index');
    }

    /**
     * @return array{enabled: bool, configurationIdentifier: string, cacheLifetime: int}
     */
    private function getRuntimeConfiguration(): array
    {
        $configuration = $this->selectedSite?->getSolrConfiguration();

        return [
            'enabled' => $configuration !== null && (bool)$configuration->getValueByPathOrDefaultValue(
                'plugin.tx_solr.search.llmQueryEnhancer.enabled',
                false,
            ),
            'configurationIdentifier' => $configuration !== null
                ? (string)$configuration->getValueByPathOrDefaultValue(
                    'plugin.tx_solr.search.llmQueryEnhancer.configurationIdentifier',
                    LlmQueryEnhancerService::DEFAULT_CONFIGURATION_IDENTIFIER,
                )
                : LlmQueryEnhancerService::DEFAULT_CONFIGURATION_IDENTIFIER,
            'cacheLifetime' => $configuration !== null
                ? (int)$configuration->getValueByPathOrDefaultValue(
                    'plugin.tx_solr.search.llmQueryEnhancer.cacheLifetime',
                    86400,
                )
                : 86400,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $configurations
     * @return array<string, string>
     */
    private function buildConfigurationOptions(array $configurations): array
    {
        $options = [];
        foreach ($configurations as $configuration) {
            $identifier = (string)($configuration['identifier'] ?? '');
            if ($identifier === '') {
                continue;
            }

            $name = (string)($configuration['name'] ?? '');
            $options[$identifier] = $name !== ''
                ? $name . ' (' . $identifier . ')'
                : $identifier;
        }

        return $options;
    }

    private function getBackendModuleUri(string $routeIdentifier): string
    {
        try {
            return (string)$this->backendUriBuilder->buildUriFromRoute($routeIdentifier);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param array{message?: string, messageKey?: string} $connectionTest
     */
    private function translateConnectionTestMessage(array $connectionTest): string
    {
        $message = trim((string)($connectionTest['message'] ?? ''));
        if ($message !== '') {
            return $message;
        }

        $messageKey = trim((string)($connectionTest['messageKey'] ?? ''));
        return $messageKey !== '' ? $this->translate($messageKey) : '';
    }

    private function translate(string $key): string
    {
        return LocalizationUtility::translate($key, self::LANGUAGE_DOMAIN) ?? $key;
    }
}
