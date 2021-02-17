<?php
namespace ApacheSolrForTypo3\Solr\FrontendEnvironment;

use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationPageResolver;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
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
     * @param int $pageId Id of the (root) page to get the Solr configuration from.
     * @param string $path The TypoScript configuration path to retrieve.
     * @param int $language System language uid, optional, defaults to 0
     * @return TypoScriptConfiguration The Solr configuration for the requested tree.
     */
    public function getConfigurationFromPageId($pageId, $path, $language = 0)
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
    private function getConfigurationPageIdToUse($pageId)
    {
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        if ($extensionConfiguration->getIsUseConfigurationFromClosestTemplateEnabled()) {
            /** @var $configurationPageResolve ConfigurationPageResolver */
            $configurationPageResolver = GeneralUtility::makeInstance(ConfigurationPageResolver::class);
            $pageId = $configurationPageResolver->getClosestPageIdWithActiveTemplate($pageId);
            return $pageId;
        }
        return $pageId;
    }

    /**
     * builds an configuration array, containing the solr configuration.
     *
     * @param integer $pageId
     * @param string $path
     * @param integer $language
     * @return array
     */
    protected function buildConfigurationArray($pageId, $path, $language)
    {
        if (is_int($language)) {
            GeneralUtility::makeInstance(FrontendEnvironment::class)->changeLanguageContext((int)$pageId, (int)$language);
        }
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageId);
        try {
            $rootLine = $rootlineUtility->get();
        } catch (\RuntimeException $e) {
            $rootLine = [];
        }

        /** @var $tmpl ExtendedTemplateService */
        $tmpl = GeneralUtility::makeInstance(ExtendedTemplateService::class);
        $tmpl->tt_track = false; // Do not log time-performance information
        $tmpl->runThroughTemplates($rootLine); // This generates the constants/config + hierarchy info for the template.
        $tmpl->generateConfig();

        $getConfigurationFromInitializedTSFEAndWriteToCache = $tmpl->ext_getSetup($tmpl->setup, $path);
        $configurationToUse = $getConfigurationFromInitializedTSFEAndWriteToCache[0];

        return is_array($configurationToUse) ? $configurationToUse : [];
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
    protected function buildTypoScriptConfigurationFromArray(array $configurationToUse, $pageId, $languageId, $typoScriptPath)
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        return $configurationManager->getTypoScriptConfiguration($configurationToUse, $pageId, $languageId, $typoScriptPath);
    }

}