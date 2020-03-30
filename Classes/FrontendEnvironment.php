<?php
namespace ApacheSolrForTypo3\Solr;


use ApacheSolrForTypo3\Solr\FrontendEnvironment\Tsfe;
use ApacheSolrForTypo3\Solr\FrontendEnvironment\TypoScript;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;


class FrontendEnvironment implements SingletonInterface
{

    /**
     * @var TypoScript
     */
    private $typoScript = null;

    /**
     * @var Tsfe
     */
    private $tsfe = null;

    public function __construct(Tsfe $tsfe = null, TypoScript $typoScript = null)
    {
        $this->tsfe = $tsfe ?? GeneralUtility::makeInstance(Tsfe::class);
        $this->typoScript = $typoScript ?? GeneralUtility::makeInstance(TypoScript::class);
    }

    public function changeLanguageContext(int $pageId, int $language): void
    {
        $this->tsfe->changeLanguageContext($pageId, $language);
    }

    /**
     * Initializes the TSFE for a given page ID and language.
     *
     * @param $pageId
     * @param int $language
     * @throws SiteNotFoundException
     * @throws \TYPO3\CMS\Core\Error\Http\ServiceUnavailableException
     * @throws \TYPO3\CMS\Core\Http\ImmediateResponseException
     */
    public function initializeTsfe($pageId, $language = 0)
    {
        $this->tsfe->initializeTsfe($pageId, $language);
    }

    public function getConfigurationFromPageId($pageId, $path, $language = 0)
    {
        return $this->typoScript->getConfigurationFromPageId($pageId, $path, $language);
    }

    public function isAllowedPageType(array $pageRecord, $configurationName = 'pages'): bool
    {
        $configuration = $this->getConfigurationFromPageId($pageRecord['uid'], '');
        $allowedPageTypes = $configuration->getIndexQueueAllowedPageTypesArrayByConfigurationName($configurationName);
        return in_array($pageRecord['doktype'], $allowedPageTypes);
    }

    public function getSolrConfigurationFromPageId($pageId, $language = 0)
    {
        return $this->getConfigurationFromPageId($pageId, '', $language);
    }
}