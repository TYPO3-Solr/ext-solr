<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Utility\EidUtility;
use TYPO3\CMS\Core\Core\Bootstrap;

/**
 * Class TSFETestBootstrapper
 * @package ApacheSolrForTypo3\Solr\Tests\Integration
 */
class TSFETestBootstrapper
{
    /**
     * @return TSFEBootstrapResult
     */
    public function run($TYPO3_CONF_VARS = [], $id = 1, $type = 0, $no_cache = '', $cHash = '', $_2 = null, $MP = '', $RDCT = '', $config = [])
    {
        $result = new TSFEBootstrapResult();

        /** @var $TSFE \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController */
        $TSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $TYPO3_CONF_VARS, $id, $type, $no_cache, $cHash, $_2, $MP, $RDCT);
        $TSFE->set_no_cache();
        $GLOBALS['TSFE'] = $TSFE;


        EidUtility::initLanguage();

        $TSFE->id = $id;
        $TSFE->initFEuser();
        $TSFE->checkAlternativeIdMethods();
        $TSFE->clear_preview();

        try {
            $TSFE->determineId();
        } catch (\TYPO3\CMS\Core\Http\ImmediateResponseException $e) {
            $result->addExceptions($e);
        }

        $TSFE->initTemplate();
        $TSFE->getConfigArray();
        $TSFE->config = array_merge($TSFE->config, $config);

        Bootstrap::getInstance();

        // only needed for FrontendGroupRestriction.php
        $GLOBALS['TSFE']->gr_list =  $TSFE->gr_list;
        $TSFE->settingLanguage();
        $TSFE->settingLocale();

        $result->setTsfe($TSFE);

        return $result;
    }
}