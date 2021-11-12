<?php
namespace ApacheSolrForTypo3\Solr\FrontendEnvironment;

use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationPageResolver;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use RuntimeException;
use stdClass;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Error\Http\InternalServerErrorException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TypoScript implements SingletonInterface
{

    private $configurationObjectCache = [];


    /**
     * Loads the TypoScript configuration for a given page id and language.
     * Language usage may be disabled to get the default TypoScript
     * configuration.
     *
     * @param int $pageId The page id of the (root) page to get the Solr configuration from.
     * @param string $path The TypoScript configuration path to retrieve.
     * @param int $language System language uid, optional, defaults to 0
     * @return TypoScriptConfiguration The Solr configuration for the requested tree.
     */
    public function getConfigurationFromPageId(int $pageId, string $path, int $language = 0): TypoScriptConfiguration
    {
        $pageId = $this->getConfigurationPageIdToUse($pageId);

        $cacheId = md5($pageId . '|' . $path . '|' . $language);
        if (isset($this->configurationObjectCache[$cacheId])) {
            return $this->configurationObjectCache[$cacheId];
        }

        // If we're on UID 0, we cannot retrieve a configuration currently.
        // getRootline() below throws an exception (since #typo3-60 )
        // as UID 0 cannot have any parent rootline by design.
        if ($pageId == 0) {
            return $this->configurationObjectCache[$cacheId] = $this->buildTypoScriptConfigurationFromArray([], $pageId, $language, $path);
        }

        /** @var $cache TwoLevelCache */
        $cache = GeneralUtility::makeInstance(TwoLevelCache::class, /** @scrutinizer ignore-type */ 'tx_solr_configuration');
        $configurationArray = $cache->get($cacheId);


        if (!empty($configurationArray)) {
            // we have a cache hit and can return it.
            return $this->configurationObjectCache[$cacheId] = $this->buildTypoScriptConfigurationFromArray($configurationArray, $pageId, $language, $path);
        }

        // we have nothing in the cache. We need to build the configurationToUse
        $configurationArray = $this->buildConfigurationArray($pageId, $path, $language);

        $cache->set($cacheId, $configurationArray);

        return $this->configurationObjectCache[$cacheId] = $this->buildTypoScriptConfigurationFromArray($configurationArray, $pageId, $language, $path);
    }

    /**
     * This method retrieves the closest pageId where a configuration is located, when this
     * feature is enabled.
     *
     * @param int $pageId
     * @return int
     */
    private function getConfigurationPageIdToUse(int $pageId): int
    {
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $pageRecord = BackendUtility::getRecord('pages', $pageId);
        $isSpacerOrSysfolder = ($pageRecord['doktype'] ?? null) == PageRepository::DOKTYPE_SPACER || ($pageRecord['doktype'] ?? null) == PageRepository::DOKTYPE_SYSFOLDER;
        if ($extensionConfiguration->getIsUseConfigurationFromClosestTemplateEnabled() || $isSpacerOrSysfolder === true) {
            /* @var ConfigurationPageResolver $configurationPageResolve */
            $configurationPageResolver = GeneralUtility::makeInstance(ConfigurationPageResolver::class);
            return $configurationPageResolver->getClosestPageIdWithActiveTemplate($pageId);
        }
        return $pageId;
    }

    /**
     * Builds a configuration array, containing the solr configuration.
     *
     * @param int $pageId
     * @param string $path
     * @param int $language
     * @return array
     */
    protected function buildConfigurationArray(int $pageId, string $path, int $language): array
    {
        /* @var Tsfe $tsfeManager */
        $tsfeManager = GeneralUtility::makeInstance(Tsfe::class);
        try {
            $tsfe = $tsfeManager->getTsfeByPageIdAndLanguageId($pageId, $language);
        } catch (InternalServerErrorException | ServiceUnavailableException | SiteNotFoundException $e) {
            // @todo logging!
            return [];
        }
        $getConfigurationFromInitializedTSFEAndWriteToCache = $this->ext_getSetup($tsfe->tmpl->setup, $path);
        return $getConfigurationFromInitializedTSFEAndWriteToCache[0] ?? [];
    }


    /**
     * @param array $theSetup
     * @param string $theKey
     * @return array
     */
    public function ext_getSetup(array $theSetup, string $theKey): array
    {
        $parts = explode('.', $theKey, 2);
        if ((string)$parts[0] !== '' && is_array($theSetup[$parts[0] . '.'])) {
            if (trim($parts[1]) !== '') {
                return $this->ext_getSetup($theSetup[$parts[0] . '.'], trim($parts[1]));
            }
            return [$theSetup[$parts[0] . '.'], $theSetup[$parts[0]]];
        }
        if (trim($theKey) !== '') {
            return [[], $theSetup[$theKey]];
        }
        return [$theSetup, ''];
    }

    /**
     * Builds the configuration object from a config array and returns it.
     *
     * @param array $configurationToUse
     * @param int $pageId
     * @param int $languageId
     * @param string $typoScriptPath
     * @return TypoScriptConfiguration
     */
    protected function buildTypoScriptConfigurationFromArray(array $configurationToUse, int $pageId, int $languageId, string $typoScriptPath): TypoScriptConfiguration
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        return $configurationManager->getTypoScriptConfiguration($configurationToUse, $pageId, $languageId, $typoScriptPath);
    }

}
