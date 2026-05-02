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
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsFilterDto;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\Report\AccessFilterPluginInstalledStatus;
use ApacheSolrForTypo3\Solr\Report\AllowUrlFOpenStatus;
use ApacheSolrForTypo3\Solr\Report\SchemaStatus;
use ApacheSolrForTypo3\Solr\Report\SiteHandlingStatus;
use ApacheSolrForTypo3\Solr\Report\SolrConfigStatus;
use ApacheSolrForTypo3\Solr\Report\SolrConfigurationStatus;
use ApacheSolrForTypo3\Solr\Report\SolrStatus;
use ApacheSolrForTypo3\Solr\Report\SolrVersionStatus;
use ApacheSolrForTypo3\Solr\Report\TextToVectorModelStoreStatus;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use ApacheSolrForTypo3\Solr\System\Validator\Path;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Reports\Status;
use Throwable;

/**
 * Info Module
 */
class InfoModuleController extends AbstractModuleController
{
    private const LANGUAGE_DOMAIN = 'solr.modules.info';

    private const SOLR_REPORT_PROVIDERS = [
        AccessFilterPluginInstalledStatus::class,
        SolrConfigurationStatus::class,
        SolrStatus::class,
        SchemaStatus::class,
        SiteHandlingStatus::class,
        TextToVectorModelStoreStatus::class,
        SolrVersionStatus::class,
        SolrConfigStatus::class,
        AllowUrlFOpenStatus::class,
    ];

    private const SOLR_REPORT_PREVIEW_LIMIT = 3;

    protected ApacheSolrDocumentRepository $apacheSolrDocumentRepository;

    private array $browserEndpointProbeResults = [];

    private array $coreDocumentCountByEndpoint = [];

    private array $siteDocumentCountByEndpoint = [];

    private ?array $solrServerInfo = null;

    /**
     * @inheritDoc
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();
        $this->apacheSolrDocumentRepository = GeneralUtility::makeInstance(ApacheSolrDocumentRepository::class);
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
        $this->collectSolrReportInfo();

        return $this->moduleTemplate->renderResponse('Backend/Search/InfoModule/Index');
    }

    private function collectSolrReportInfo(): void
    {
        $groups = [];
        $statusCount = 0;
        $issueCount = 0;
        $highestSeverity = ContextualFeedbackSeverity::NOTICE;

        foreach (self::SOLR_REPORT_PROVIDERS as $providerClass) {
            $group = $this->buildSolrReportGroup($providerClass);
            $groups[] = $group;
            $statusCount += $group['statusCount'];
            $issueCount += $group['issueCount'];

            if ($group['severityValue'] > $highestSeverity->value) {
                $highestSeverity = ContextualFeedbackSeverity::from($group['severityValue']);
            }
        }

        $this->moduleTemplate->assignMultiple([
            'solrReportGroups' => $groups,
            'solrReportSummary' => [
                'statusCount' => $statusCount,
                'issueCount' => $issueCount,
                'statusClass' => $highestSeverity->getCssClass(),
                'statusLabelKey' => $this->getReportSeverityLabelKey($highestSeverity),
            ],
        ]);
    }

    /**
     * @return array{
     *     label: string,
     *     statusClass: string,
     *     statusLabelKey: string,
     *     severityValue: int,
     *     icon: string,
     *     statusCount: int,
     *     issueCount: int,
     *     hiddenIssueCount: int,
     *     previewItems: array<int, array<string, mixed>>,
     *     displayItems: array<int, array<string, mixed>>
     * }
     */
    private function buildSolrReportGroup(string $providerClass): array
    {
        $providerLabel = $providerClass;
        $statuses = [];

        try {
            $provider = GeneralUtility::makeInstance(
                $providerClass,
                GeneralUtility::makeInstance(ViewFactoryInterface::class),
            );
            $providerLabel = $this->getLanguageService()->sL($provider->getLabel()) ?: $providerClass;
            $statuses = $provider->getStatus();
        } catch (Throwable $exception) {
            $statuses = [
                GeneralUtility::makeInstance(
                    Status::class,
                    $this->translateInfoLabel('reports.providerUnavailable.title'),
                    $this->translateInfoLabel('reports.providerUnavailable.value'),
                    $this->translateInfoLabel('reports.providerUnavailable.message', [
                        'message' => $this->shortenErrorMessage($exception->getMessage()),
                    ]),
                    ContextualFeedbackSeverity::ERROR,
                ),
            ];
        }

        $highestSeverity = $this->getHighestReportSeverity($statuses);
        $items = $this->buildSolrReportItems($providerClass, $statuses);
        $issueItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => $item['severityValue'] > ContextualFeedbackSeverity::OK->value,
        ));
        $previewItems = array_slice($issueItems, 0, self::SOLR_REPORT_PREVIEW_LIMIT);

        return [
            'label' => $providerLabel,
            'statusClass' => $highestSeverity->getCssClass(),
            'statusLabelKey' => $this->getReportSeverityLabelKey($highestSeverity),
            'severityValue' => $highestSeverity->value,
            'icon' => $highestSeverity->getIconIdentifier(),
            'statusCount' => count($statuses),
            'issueCount' => count($issueItems),
            'hiddenIssueCount' => max(count($issueItems) - count($previewItems), 0),
            'previewItems' => $previewItems,
            'displayItems' => $items,
        ];
    }

    /**
     * @param Status[] $statuses
     * @return array<int, array<string, mixed>>
     */
    private function buildSolrReportItems(string $providerClass, array $statuses): array
    {
        $items = [];
        foreach ($statuses as $status) {
            if (!$status instanceof Status) {
                continue;
            }

            $severity = $status->getSeverity();
            $items[] = [
                'title' => $status->getTitle(),
                'value' => $status->getValue(),
                'message' => $this->normalizeReportMessage($status->getMessage()),
                'statusClass' => $severity->getCssClass(),
                'statusLabelKey' => $this->getReportSeverityLabelKey($severity),
                'severityValue' => $severity->value,
                'icon' => $severity->getIconIdentifier(),
                'hintKey' => $this->getReportHintKey($providerClass, $severity),
            ];
        }

        return $items;
    }

    /**
     * @param Status[] $statuses
     */
    private function getHighestReportSeverity(array $statuses): ContextualFeedbackSeverity
    {
        $highestSeverity = ContextualFeedbackSeverity::NOTICE;
        foreach ($statuses as $status) {
            if ($status instanceof Status && $status->getSeverity()->value > $highestSeverity->value) {
                $highestSeverity = $status->getSeverity();
            }
        }

        return $highestSeverity;
    }

    private function getReportSeverityLabelKey(ContextualFeedbackSeverity $severity): string
    {
        return match ($severity) {
            ContextualFeedbackSeverity::OK => 'reports.severity.ok',
            ContextualFeedbackSeverity::WARNING => 'reports.severity.warning',
            ContextualFeedbackSeverity::ERROR => 'reports.severity.error',
            ContextualFeedbackSeverity::INFO => 'reports.severity.info',
            ContextualFeedbackSeverity::NOTICE => 'reports.severity.notice',
        };
    }

    private function getReportHintKey(string $providerClass, ContextualFeedbackSeverity $severity): string
    {
        if ($severity->value <= ContextualFeedbackSeverity::OK->value) {
            return '';
        }

        return match ($providerClass) {
            AccessFilterPluginInstalledStatus::class => 'reports.hint.accessFilter',
            SolrConfigurationStatus::class => 'reports.hint.configuration',
            SolrStatus::class => 'reports.hint.connection',
            SchemaStatus::class => 'reports.hint.schema',
            SiteHandlingStatus::class => 'reports.hint.siteHandling',
            TextToVectorModelStoreStatus::class => 'reports.hint.vector',
            SolrVersionStatus::class => 'reports.hint.solrVersion',
            SolrConfigStatus::class => 'reports.hint.solrConfig',
            AllowUrlFOpenStatus::class => 'reports.hint.allowUrlFopen',
            default => 'reports.hint.default',
        };
    }

    private function normalizeReportMessage(string $message): string
    {
        $message = str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $message);
        $message = html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $message = trim((string)preg_replace('/\s+/', ' ', $message));

        if (strlen($message) <= 280) {
            return $message;
        }

        return substr($message, 0, 277) . '...';
    }

    private function translateInfoLabel(string $key, array $arguments = []): string
    {
        return LocalizationUtility::translate($key, self::LANGUAGE_DOMAIN, $arguments) ?? $key;
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
        $connectionSummaries = [];
        $configuredCoreDocumentTotal = 0;
        $configuredCoreDocumentTotalAvailable = true;
        $this->solrServerInfo = null;

        /** @var Path $path */
        $path = GeneralUtility::makeInstance(Path::class);
        $connections = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);

        if (empty($connections)) {
            $this->moduleTemplate->assign('can_not_proceed', true);
            return;
        }

        $alreadyListedConnections = [];
        foreach ($connections as $languageId => $connection) {
            $coreAdmin = $connection->getAdminService();
            $coreUrl = (string)$coreAdmin;
            if (in_array($coreUrl, $alreadyListedConnections)) {
                continue;
            }
            $alreadyListedConnections[] = $coreUrl;

            $pingResult = $this->pingSolrEndpoint($coreAdmin, $coreUrl);
            $isConnected = $pingResult['isConnected'];
            if ($isConnected) {
                $connectedHosts[] = $coreUrl;
            } else {
                $missingHosts[] = $coreUrl;
            }

            if (!$path->isValidSolrPath($coreAdmin->getCorePath())) {
                $invalidPaths[] = $coreAdmin->getCorePath();
            }

            if ($this->solrServerInfo === null) {
                $this->solrServerInfo = $this->getSolrServerInfo($coreUrl);
            }

            $coreName = $this->getCoreNameFromPath($coreAdmin->getCorePath());
            $coreDocumentCount = $this->getCoreDocumentCountForEndpoint($coreUrl);
            $siteDocumentCount = $this->getSiteDocumentCountForEndpoint($coreUrl);
            $browserEndpoints = $this->getBrowserEndpointLinks($coreUrl);
            $browserEndpointStatus = $this->getBrowserEndpointStatus($browserEndpoints);
            $documents = $this->apacheSolrDocumentRepository->findByPageIdAndByLanguageId($this->selectedPageUID, (int)$languageId);
            $pageDocumentCount = count($documents);
            if ($siteDocumentCount === null) {
                $configuredCoreDocumentTotalAvailable = false;
            } else {
                $configuredCoreDocumentTotal += $siteDocumentCount;
            }
            $statusClass = $this->getConnectionStatusClass($isConnected, $coreDocumentCount, $browserEndpointStatus);
            $connectionSummaries[] = [
                'corePath' => $coreAdmin->getCorePath(),
                'coreName' => $coreName,
                'endpoint' => $coreUrl,
                'browserEndpoints' => $browserEndpoints,
                'statusClass' => $statusClass,
                'statusIconIdentifier' => $this->getConnectionStatusIconIdentifier($statusClass),
                'statusMessageRole' => $statusClass === 'danger' ? 'alert' : 'status',
                'statusPillState' => $this->getConnectionStatusPillState($isConnected, $coreDocumentCount, $browserEndpointStatus),
                'statusMessageTitleKey' => $this->getConnectionStatusMessageTitleKey($isConnected, $coreDocumentCount, $browserEndpointStatus),
                'statusReasonKey' => $this->getConnectionStatusReasonKey($isConnected, $coreDocumentCount, $browserEndpointStatus),
                'connectionErrorKey' => $pingResult['errorKey'],
                'connectionErrorArguments' => $pingResult['errorArguments'],
                'isConnected' => $isConnected,
                'languageId' => (int)$languageId,
                'languageTitle' => $this->getLanguageTitle((int)$languageId),
                'documentCount' => $siteDocumentCount,
                'coreDocumentCount' => $coreDocumentCount,
                'documentCountAvailable' => $siteDocumentCount !== null,
                'coreDocumentCountAvailable' => $coreDocumentCount !== null,
                'pageDocumentCount' => $pageDocumentCount,
            ];
        }

        $this->moduleTemplate->assignMultiple([
            'site' => $this->selectedSite,
            'apiKey' => Api::getApiKey(),
            'connectedHosts' => $connectedHosts,
            'missingHosts' => $missingHosts,
            'invalidPaths' => $invalidPaths,
            'connectionSummaries' => $connectionSummaries,
            'configuredCoreCount' => count($connectionSummaries),
            'indexedDocumentTotal' => $configuredCoreDocumentTotal,
            'indexedDocumentTotalAvailable' => $configuredCoreDocumentTotalAvailable,
            'solrServerInfo' => $this->solrServerInfo,
        ]);
    }

    private function getConnectionStatusClass(bool $isConnected, ?int $documentCount, string $browserEndpointStatus): string
    {
        if (!$isConnected || $documentCount === null) {
            return 'danger';
        }

        if ($browserEndpointStatus === 'unreachable') {
            return 'danger';
        }

        if ($browserEndpointStatus === 'partial') {
            return 'warning';
        }

        return ($documentCount ?? 0) > 0 ? 'success' : 'warning';
    }

    private function getConnectionStatusIconIdentifier(string $statusClass): string
    {
        return match ($statusClass) {
            'success' => 'status-dialog-ok',
            'warning' => 'status-dialog-warning',
            'danger' => 'status-dialog-error',
            default => 'status-dialog-information',
        };
    }

    private function getConnectionStatusPillState(bool $isConnected, ?int $documentCount, string $browserEndpointStatus): string
    {
        if (!$isConnected || $documentCount === null || $browserEndpointStatus === 'unreachable') {
            return 'error';
        }

        if ($browserEndpointStatus === 'partial' || ($documentCount ?? 0) === 0) {
            return 'check';
        }

        return 'ok';
    }

    private function getConnectionStatusMessageTitleKey(bool $isConnected, ?int $documentCount, string $browserEndpointStatus): string
    {
        if (!$isConnected) {
            return 'connections.status.coreEndpointUnavailableTitle';
        }

        if ($documentCount === null) {
            return 'connections.status.documentCountUnavailableTitle';
        }

        if ($browserEndpointStatus === 'unreachable') {
            return 'connections.status.adminUiUnavailableTitle';
        }

        if ($browserEndpointStatus === 'partial') {
            return 'connections.status.adminUiPartialTitle';
        }

        if (($documentCount ?? 0) === 0) {
            return 'connections.status.warningTitle';
        }

        return 'connections.status.okTitle';
    }

    private function getConnectionStatusReasonKey(bool $isConnected, ?int $documentCount, string $browserEndpointStatus): string
    {
        if (!$isConnected) {
            return 'connections.status.reason.notReachable';
        }

        if ($documentCount === null) {
            return 'connections.status.reason.documentCountUnavailable';
        }

        if ($browserEndpointStatus === 'unreachable') {
            return 'connections.status.reason.adminUiUnavailable';
        }

        if ($browserEndpointStatus === 'partial') {
            return 'connections.status.reason.adminUiPartial';
        }

        if (($documentCount ?? 0) === 0) {
            return 'connections.status.reason.noDocuments';
        }

        return 'connections.status.reason.ok';
    }

    private function getCoreNameFromPath(string $corePath): string
    {
        return trim($corePath, '/');
    }

    /**
     * @param array<int, array{labelKey: string, url: string, probeUrl: string, isReachable: bool}> $browserEndpoints
     */
    private function getBrowserEndpointStatus(array $browserEndpoints): string
    {
        if ($browserEndpoints === []) {
            return 'unavailable';
        }

        $reachableCount = count(array_filter($browserEndpoints, static fn (array $endpoint): bool => (bool)$endpoint['isReachable']));
        if ($reachableCount === 0) {
            return 'unreachable';
        }

        if ($reachableCount < count($browserEndpoints)) {
            return 'partial';
        }

        return 'reachable';
    }

    /**
     * @return array{isConnected: bool, errorKey: string|null, errorArguments: array<string, string>}
     */
    private function pingSolrEndpoint(SolrAdminService $coreAdmin, string $endpointUrl): array
    {
        $validationError = $this->getSolrEndpointValidationError($endpointUrl);
        if ($validationError !== null) {
            return [
                'isConnected' => false,
                'errorKey' => $validationError['key'],
                'errorArguments' => $validationError['arguments'],
            ];
        }

        try {
            $isConnected = $coreAdmin->ping();
            return [
                'isConnected' => $isConnected,
                'errorKey' => $isConnected ? null : 'connections.status.validation.pingFailed',
                'errorArguments' => [],
            ];
        } catch (\Throwable $exception) {
            return [
                'isConnected' => false,
                'errorKey' => 'connections.status.validation.exception',
                'errorArguments' => [
                    'error' => $this->shortenErrorMessage($exception->getMessage()),
                ],
            ];
        }
    }

    /**
     * @return array{key: string, arguments: array<string, string>}|null
     */
    private function getSolrEndpointValidationError(string $endpointUrl): ?array
    {
        $endpointUrl = trim($endpointUrl);
        if ($endpointUrl === '') {
            return [
                'key' => 'connections.status.validation.empty',
                'arguments' => [],
            ];
        }

        $rawPort = $this->extractRawPort($endpointUrl);
        if ($rawPort !== null && ($rawPort < 1 || $rawPort > 65535)) {
            return [
                'key' => 'connections.status.validation.invalidPort',
                'arguments' => [
                    'port' => (string)$rawPort,
                ],
            ];
        }

        $endpointParts = parse_url($endpointUrl);
        if (!is_array($endpointParts) || empty($endpointParts['scheme']) || empty($endpointParts['host'])) {
            return [
                'key' => 'connections.status.validation.malformed',
                'arguments' => [],
            ];
        }

        $scheme = strtolower((string)$endpointParts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return [
                'key' => 'connections.status.validation.unsupportedScheme',
                'arguments' => [
                    'scheme' => $scheme,
                ],
            ];
        }

        return null;
    }

    private function extractRawPort(string $url): ?int
    {
        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\/(?:\[[^\]]+\]|[^\/?#:]+):(\d+)/i', $url, $matches) !== 1) {
            return null;
        }

        return (int)$matches[1];
    }

    private function shortenErrorMessage(string $message): string
    {
        $message = trim((string)preg_replace('/\s+/', ' ', $message));
        if (strlen($message) <= 180) {
            return $message;
        }

        return substr($message, 0, 177) . '...';
    }

    /**
     * @return array<int, array{labelKey: string, url: string, probeUrl: string, isReachable: bool}>
     */
    private function getBrowserEndpointLinks(string $endpointUrl): array
    {
        if ($this->getSolrEndpointValidationError($endpointUrl) !== null) {
            return [];
        }

        $endpointParts = parse_url($endpointUrl);
        if (!is_array($endpointParts) || empty($endpointParts['host'])) {
            return [
                [
                    'labelKey' => 'connections.browserEndpoint.configured',
                    'url' => $endpointUrl,
                    'probeUrl' => $endpointUrl,
                    'isReachable' => $this->isBrowserEndpointReachable($endpointUrl),
                ],
            ];
        }

        $currentHost = GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY');
        if (
            $currentHost === ''
            || !$this->isContainerInternalSolrHost($endpointParts['host'])
            || !str_ends_with($currentHost, '.ddev.site')
        ) {
            $probeUrl = $this->buildConfiguredPingUrl($endpointParts);
            return [
                [
                    'labelKey' => 'connections.browserEndpoint.configured',
                    'url' => $endpointUrl,
                    'probeUrl' => $probeUrl,
                    'isReachable' => $this->isBrowserEndpointReachable($probeUrl),
                ],
            ];
        }

        $path = $endpointParts['path'] ?? '/solr/';
        $pathSegments = array_values(array_filter(explode('/', trim($path, '/'))));
        $coreName = array_pop($pathSegments);
        $solrBasePath = '/' . implode('/', $pathSegments) . '/';
        if ($solrBasePath === '//') {
            $solrBasePath = '/solr/';
        }

        $candidates = [
            [
                'labelKey' => 'connections.browserEndpoint.https',
                'displayUrl' => $this->buildSolrAdminUrl('https', $currentHost, 8984, $solrBasePath, $coreName),
                'probeUrl' => $this->buildSolrPingUrl('https', $currentHost, 8984, $solrBasePath, $coreName),
            ],
            [
                'labelKey' => 'connections.browserEndpoint.http',
                'displayUrl' => $this->buildSolrAdminUrl('http', $currentHost, 8983, $solrBasePath, $coreName),
                'probeUrl' => $this->buildSolrPingUrl('http', $currentHost, 8983, $solrBasePath, $coreName),
            ],
        ];

        return array_map(
            fn (array $candidate): array => [
                'labelKey' => $candidate['labelKey'],
                'url' => $candidate['displayUrl'],
                'probeUrl' => $candidate['probeUrl'],
                'isReachable' => $this->isBrowserEndpointReachable($candidate['probeUrl']),
            ],
            $candidates,
        );
    }

    private function isContainerInternalSolrHost(string $host): bool
    {
        return $host === 'typo3-solr' || str_ends_with($host, '-typo3-solr');
    }

    /**
     * @return array{
     *     isAvailable: bool,
     *     statusClass: string,
     *     adminEndpoints: array<int, array{labelKey: string, url: string, probeUrl: string, isReachable: bool}>,
     *     metrics: array<int, array{labelKey: string, value: string}>,
     *     coreCount: int,
     *     documentCount: int,
     *     coreDocumentCounts: array<string, int>
     * }|null
     */
    private function getSolrServerInfo(string $endpointUrl): ?array
    {
        $endpointContext = $this->getSolrEndpointContext($endpointUrl);
        if ($endpointContext === null) {
            return null;
        }

        $systemInfo = $this->fetchSolrJson(
            $this->buildSolrApiUrl(
                $endpointContext['scheme'],
                $endpointContext['host'],
                $endpointContext['port'],
                $endpointContext['solrBasePath'],
                'admin/info/system',
                ['wt' => 'json'],
            ),
        );
        $coreStatus = $this->fetchSolrJson(
            $this->buildSolrApiUrl(
                $endpointContext['scheme'],
                $endpointContext['host'],
                $endpointContext['port'],
                $endpointContext['solrBasePath'],
                'admin/cores',
                ['action' => 'STATUS', 'wt' => 'json'],
            ),
        );

        $hasServerInfo = is_array($systemInfo) && is_array($coreStatus);
        $cores = $hasServerInfo && is_array($coreStatus['status'] ?? null) ? $coreStatus['status'] : [];
        $documentCount = 0;
        $coreDocumentCounts = [];
        foreach ($cores as $coreName => $core) {
            if (is_array($core) && isset($core['index']['numDocs'])) {
                $coreDocumentCount = (int)$core['index']['numDocs'];
                $documentCount += $coreDocumentCount;
                $coreDocumentCounts[(string)$coreName] = $coreDocumentCount;
            }
        }

        $metrics = [];
        if (is_array($systemInfo)) {
            $lucene = is_array($systemInfo['lucene'] ?? null) ? $systemInfo['lucene'] : [];
            $jvm = is_array($systemInfo['jvm'] ?? null) ? $systemInfo['jvm'] : [];
            $jre = is_array($jvm['jre'] ?? null) ? $jvm['jre'] : [];
            $memory = is_array($jvm['memory'] ?? null) ? $jvm['memory'] : [];
            $memoryRaw = is_array($memory['raw'] ?? null) ? $memory['raw'] : [];
            $jmx = is_array($jvm['jmx'] ?? null) ? $jvm['jmx'] : [];

            $metrics = array_values(array_filter([
                $this->buildServerMetric('connections.serverInfo.solrVersion', $lucene['solr-spec-version'] ?? ''),
                $this->buildServerMetric('connections.serverInfo.luceneVersion', $lucene['lucene-spec-version'] ?? ''),
                $this->buildServerMetric('connections.serverInfo.javaRuntime', $jre['version'] ?? $jvm['version'] ?? ''),
                $this->buildServerMetric('connections.serverInfo.jvmMemory', $this->formatMemoryUsage($memory, $memoryRaw)),
                $this->buildServerMetric('connections.serverInfo.solrHome', $systemInfo['solr_home'] ?? ''),
                $this->buildServerMetric('connections.serverInfo.uptime', $this->formatDuration((int)($jmx['upTimeMS'] ?? 0))),
            ]));
        }

        return [
            'isAvailable' => $hasServerInfo,
            'statusClass' => $hasServerInfo ? 'success' : 'danger',
            'adminEndpoints' => $this->getBrowserAdminRootLinks($endpointContext),
            'metrics' => $metrics,
            'coreCount' => count($cores),
            'documentCount' => $documentCount,
            'coreDocumentCounts' => $coreDocumentCounts,
        ];
    }

    /**
     * @return array{labelKey: string, value: string}|null
     */
    private function buildServerMetric(string $labelKey, mixed $value): ?array
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        return [
            'labelKey' => $labelKey,
            'value' => $value,
        ];
    }

    /**
     * @param array<string, mixed> $memory
     * @param array<string, mixed> $memoryRaw
     */
    private function formatMemoryUsage(array $memory, array $memoryRaw): string
    {
        $used = trim((string)($memory['used'] ?? ''));
        $used = (string)preg_replace('/\s+\(%?[\d.,]+\)$/', '', $used);
        $max = trim((string)($memory['max'] ?? ''));
        if ($used === '' || $max === '') {
            return '';
        }

        $percentage = isset($memoryRaw['used%']) ? sprintf('%.1f%%', (float)$memoryRaw['used%']) : '';
        return trim($used . ' / ' . $max . ($percentage !== '' ? ' (' . $percentage . ')' : ''));
    }

    private function formatDuration(int $milliseconds): string
    {
        if ($milliseconds <= 0) {
            return '';
        }

        $minutes = intdiv($milliseconds, 60000);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $remainingMinutes = $minutes % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' d';
        }
        if ($hours > 0 || $days > 0) {
            $parts[] = $hours . ' h';
        }
        $parts[] = $remainingMinutes . ' min';

        return implode(' ', $parts);
    }

    /**
     * @return array{
     *     scheme: string,
     *     host: string,
     *     port: int|null,
     *     solrBasePath: string,
     *     coreName: string|null
     * }|null
     */
    private function getSolrEndpointContext(string $endpointUrl): ?array
    {
        if ($this->getSolrEndpointValidationError($endpointUrl) !== null) {
            return null;
        }

        $endpointParts = parse_url($endpointUrl);
        if (!is_array($endpointParts) || empty($endpointParts['host'])) {
            return null;
        }

        $path = (string)($endpointParts['path'] ?? '/solr/');
        $pathSegments = array_values(array_filter(explode('/', trim($path, '/'))));
        $coreName = array_pop($pathSegments);
        $solrBasePath = '/' . implode('/', $pathSegments) . '/';
        if ($solrBasePath === '//') {
            $solrBasePath = '/solr/';
        }

        return [
            'scheme' => (string)($endpointParts['scheme'] ?? 'http'),
            'host' => (string)$endpointParts['host'],
            'port' => isset($endpointParts['port']) ? (int)$endpointParts['port'] : null,
            'solrBasePath' => $solrBasePath,
            'coreName' => $coreName !== null && $coreName !== '' ? $coreName : null,
        ];
    }

    private function getCoreDocumentCountForEndpoint(string $endpointUrl): ?int
    {
        if (array_key_exists($endpointUrl, $this->coreDocumentCountByEndpoint)) {
            return $this->coreDocumentCountByEndpoint[$endpointUrl];
        }

        $endpointContext = $this->getSolrEndpointContext($endpointUrl);
        if ($endpointContext === null || $endpointContext['coreName'] === null) {
            $this->coreDocumentCountByEndpoint[$endpointUrl] = null;
            return null;
        }

        $response = $this->fetchSolrJson(
            $this->buildSolrApiUrl(
                $endpointContext['scheme'],
                $endpointContext['host'],
                $endpointContext['port'],
                $endpointContext['solrBasePath'],
                $endpointContext['coreName'] . '/select',
                [
                    'q' => '*:*',
                    'rows' => '0',
                    'wt' => 'json',
                ],
            ),
        );

        $responseBody = is_array($response['response'] ?? null) ? $response['response'] : null;
        $this->coreDocumentCountByEndpoint[$endpointUrl] = $responseBody !== null ? (int)($responseBody['numFound'] ?? 0) : null;
        return $this->coreDocumentCountByEndpoint[$endpointUrl];
    }

    private function getSiteDocumentCountForEndpoint(string $endpointUrl): ?int
    {
        if ($this->selectedSite === null) {
            return null;
        }

        $siteHash = $this->selectedSite->getSiteHash();
        $cacheKey = $endpointUrl . '|' . $siteHash;
        if (array_key_exists($cacheKey, $this->siteDocumentCountByEndpoint)) {
            return $this->siteDocumentCountByEndpoint[$cacheKey];
        }

        $endpointContext = $this->getSolrEndpointContext($endpointUrl);
        if ($endpointContext === null) {
            $this->siteDocumentCountByEndpoint[$cacheKey] = null;
            return null;
        }

        $response = $this->fetchSolrJson(
            $this->buildSolrApiUrl(
                $endpointContext['scheme'],
                $endpointContext['host'],
                $endpointContext['port'],
                $endpointContext['solrBasePath'],
                ($endpointContext['coreName'] !== null ? $endpointContext['coreName'] . '/' : '') . 'select',
                [
                    'q' => '*:*',
                    'fq' => 'siteHash:' . $siteHash,
                    'rows' => '0',
                    'wt' => 'json',
                ],
            ),
        );

        $responseBody = is_array($response['response'] ?? null) ? $response['response'] : null;
        $this->siteDocumentCountByEndpoint[$cacheKey] = $responseBody !== null ? (int)($responseBody['numFound'] ?? 0) : null;
        return $this->siteDocumentCountByEndpoint[$cacheKey];
    }

    /**
     * @param array{scheme: string, host: string, port: int|null, solrBasePath: string, coreName: string|null} $endpointContext
     * @return array<int, array{labelKey: string, url: string, probeUrl: string, isReachable: bool}>
     */
    private function getBrowserAdminRootLinks(array $endpointContext): array
    {
        $currentHost = GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY');
        if (
            $currentHost !== ''
            && $this->isContainerInternalSolrHost($endpointContext['host'])
            && str_ends_with($currentHost, '.ddev.site')
        ) {
            $candidates = [
                [
                    'labelKey' => 'connections.browserEndpoint.https',
                    'displayUrl' => $this->buildSolrRootAdminUrl('https', $currentHost, 8984, $endpointContext['solrBasePath']),
                    'probeUrl' => $this->buildSolrApiUrl('https', $currentHost, 8984, $endpointContext['solrBasePath'], 'admin/info/system', ['wt' => 'json']),
                ],
                [
                    'labelKey' => 'connections.browserEndpoint.http',
                    'displayUrl' => $this->buildSolrRootAdminUrl('http', $currentHost, 8983, $endpointContext['solrBasePath']),
                    'probeUrl' => $this->buildSolrApiUrl('http', $currentHost, 8983, $endpointContext['solrBasePath'], 'admin/info/system', ['wt' => 'json']),
                ],
            ];

            return array_map(
                fn (array $candidate): array => [
                    'labelKey' => $candidate['labelKey'],
                    'url' => $candidate['displayUrl'],
                    'probeUrl' => $candidate['probeUrl'],
                    'isReachable' => $this->isBrowserEndpointReachable($candidate['probeUrl']),
                ],
                $candidates,
            );
        }

        $probeUrl = $this->buildSolrApiUrl(
            $endpointContext['scheme'],
            $endpointContext['host'],
            $endpointContext['port'],
            $endpointContext['solrBasePath'],
            'admin/info/system',
            ['wt' => 'json'],
        );

        return [
            [
                'labelKey' => 'connections.browserEndpoint.configured',
                'url' => $this->buildSolrRootAdminUrl(
                    $endpointContext['scheme'],
                    $endpointContext['host'],
                    $endpointContext['port'],
                    $endpointContext['solrBasePath'],
                ),
                'probeUrl' => $probeUrl,
                'isReachable' => $this->isBrowserEndpointReachable($probeUrl),
            ],
        ];
    }

    private function buildSolrAdminUrl(string $scheme, string $host, int $port, string $solrBasePath, ?string $coreName): string
    {
        return $scheme
            . '://'
            . $host
            . ':' . $port
            . $solrBasePath
            . ($coreName !== null && $coreName !== '' ? '#/' . rawurlencode($coreName) . '/query?q=*:*&q.op=OR&indent=true&useParams=' : '');
    }

    private function buildSolrRootAdminUrl(string $scheme, string $host, ?int $port, string $solrBasePath): string
    {
        return $scheme
            . '://'
            . $host
            . ($port !== null ? ':' . $port : '')
            . $solrBasePath
            . '#/';
    }

    /**
     * @param array<string, string> $queryParameters
     */
    private function buildSolrApiUrl(
        string $scheme,
        string $host,
        ?int $port,
        string $solrBasePath,
        string $path,
        array $queryParameters = [],
    ): string {
        return $scheme
            . '://'
            . $host
            . ($port !== null ? ':' . $port : '')
            . rtrim($solrBasePath, '/')
            . '/'
            . ltrim($path, '/')
            . ($queryParameters !== [] ? '?' . http_build_query($queryParameters, '', '&', PHP_QUERY_RFC3986) : '');
    }

    private function buildSolrPingUrl(string $scheme, string $host, int $port, string $solrBasePath, ?string $coreName): string
    {
        return $scheme
            . '://'
            . $host
            . ':' . $port
            . $solrBasePath
            . ($coreName !== null && $coreName !== '' ? rawurlencode($coreName) . '/admin/ping' : 'admin/cores?action=STATUS&wt=json');
    }

    /**
     * @param array<string, int|string> $endpointParts
     */
    private function buildConfiguredPingUrl(array $endpointParts): string
    {
        $scheme = (string)($endpointParts['scheme'] ?? 'http');
        $host = (string)$endpointParts['host'];
        $port = isset($endpointParts['port']) ? ':' . (int)$endpointParts['port'] : '';
        $path = (string)($endpointParts['path'] ?? '/solr/');
        $query = isset($endpointParts['query']) ? '?' . $endpointParts['query'] : '';

        return $scheme . '://' . $host . $port . rtrim($path, '/') . '/admin/ping' . $query;
    }

    private function isBrowserEndpointReachable(string $probeUrl): bool
    {
        if (array_key_exists($probeUrl, $this->browserEndpointProbeResults)) {
            return $this->browserEndpointProbeResults[$probeUrl];
        }

        try {
            $response = GeneralUtility::makeInstance(RequestFactory::class)->request(
                $probeUrl,
                'GET',
                [
                    'connect_timeout' => 1,
                    'timeout' => 2,
                    'http_errors' => false,
                    'verify' => false,
                ],
            );
            $statusCode = $response->getStatusCode();
            $this->browserEndpointProbeResults[$probeUrl] = $statusCode >= 200 && $statusCode < 400;
        } catch (\Throwable) {
            $this->browserEndpointProbeResults[$probeUrl] = false;
        }

        return $this->browserEndpointProbeResults[$probeUrl];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchSolrJson(string $url): ?array
    {
        try {
            $response = GeneralUtility::makeInstance(RequestFactory::class)->request(
                $url,
                'GET',
                [
                    'connect_timeout' => 1,
                    'timeout' => 2,
                    'http_errors' => false,
                    'verify' => false,
                ],
            );
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
                return null;
            }

            $data = json_decode((string)$response->getBody(), true);
            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function getLanguageTitle(int $languageId): string
    {
        try {
            return $this->selectedSite->getTypo3SiteObject()->getLanguageById($languageId)->getTitle();
        } catch (\Throwable) {
            return (string)$languageId;
        }
    }

    /**
     * Returns the statistics
     *
     * @throws DBALException
     */
    protected function collectStatistics(?StatisticsFilterDto $statisticsFilterDto, string $operation): void
    {
        $statisticsFilter = $this->getStatisticsFilter($statisticsFilterDto, $operation);

        /** @var StatisticsRepository $statisticsRepository */
        $statisticsRepository = GeneralUtility::makeInstance(StatisticsRepository::class);

        $this->moduleTemplate->assign(
            'top_search_phrases',
            $statisticsRepository->getTopKeyWordsWithHits($statisticsFilter),
        );
        $this->moduleTemplate->assign(
            'top_search_phrases_without_hits',
            $statisticsRepository->getTopKeyWordsWithoutHits($statisticsFilter),
        );
        $this->moduleTemplate->assign(
            'search_phrases_statistics',
            $statisticsRepository->getSearchStatistics($statisticsFilter),
        );

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
            $pingResult = $this->pingSolrEndpoint($coreAdmin, (string)$coreAdmin);

            $indexFieldsInfo = [
                'corePath' => $coreAdmin->getCorePath(),
            ];
            if ($pingResult['isConnected']) {
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
            $siteDocumentCount = $this->getSiteDocumentCountForEndpoint($url);
            if ($this->solrServerInfo === null) {
                $this->solrServerInfo = $this->getSolrServerInfo($url);
            }
            $coreDocumentCount = $this->getCoreDocumentCountForEndpoint($url);
            $pageDocumentCount = $coreDocumentCount !== null ? count($documents) : null;

            $documentsByType = [];
            foreach ($documents as $document) {
                $documentsByType[$document['type']][] = $document;
            }

            $documentsByCoreAndType[$languageId]['core'] = $coreAdmin;
            $documentsByCoreAndType[$languageId]['documentCount'] = $pageDocumentCount;
            $documentsByCoreAndType[$languageId]['siteDocumentCount'] = $siteDocumentCount;
            $documentsByCoreAndType[$languageId]['coreDocumentCount'] = $coreDocumentCount;
            $documentsByCoreAndType[$languageId]['documentCountAvailable'] = $pageDocumentCount !== null;
            $documentsByCoreAndType[$languageId]['siteDocumentCountAvailable'] = $siteDocumentCount !== null;
            $documentsByCoreAndType[$languageId]['coreDocumentCountAvailable'] = $coreDocumentCount !== null;
            $documentsByCoreAndType[$languageId]['hasDocumentCountError'] = $pageDocumentCount === null
                || $siteDocumentCount === null
                || $coreDocumentCount === null;
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
