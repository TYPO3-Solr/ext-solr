<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Typo3PageIndexer;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Response;
use TYPO3\CMS\Frontend\Page\PageGenerator;

abstract class AbstractFrontendControllerTest  extends IntegrationTest {

    /**
     * @return void
     */
    public function setUp()
    {
        $_SERVER['HTTP_HOST'] = 'testone.site';
        $_SERVER['REQUEST_URI'] = '/en/search/';


        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @param $importPageIds
     */
    protected function indexPages($importPageIds)
    {
        foreach ($importPageIds as $importPageId) {
            $fakeTSFE = $this->getConfiguredTSFE([], $importPageId);
            $GLOBALS['TSFE'] = $fakeTSFE;
            $fakeTSFE->newCObj();
            $fakeTSFE->preparePageContentGeneration();
            PageGenerator::renderContent();
            /** @var $pageIndexer \ApacheSolrForTypo3\Solr\Typo3PageIndexer */
            $pageIndexer = GeneralUtility::makeInstance(Typo3PageIndexer::class, $fakeTSFE);
            $pageIndexer->indexPage();
        }

        /** @var $beUser  \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $beUser;
        $this->waitToBeVisibleInSolr();
    }

    /**
     * @param string $controllerName
     * @param string $actionName
     * @param string $plugin
     * @return Request
     */
    protected function getPreparedRequest($controllerName = 'Search', $actionName = 'results', $plugin = 'pi_result')
    {
        /** @var Request $request */
        $request = $this->objectManager->get(Request::class);
        $request->setControllerName($controllerName);
        $request->setControllerActionName($actionName);
        $request->setControllerVendorName('ApacheSolrForTypo3');
        $request->setPluginName($plugin);
        $request->setFormat('html');
        $request->setControllerExtensionName('Solr');

        return $request;
    }


    /**
     * @return Response
     */
    protected function getPreparedResponse()
    {
        /** @var $response Response */
        $response = $this->objectManager->get(Response::class);

        return $response;
    }
}