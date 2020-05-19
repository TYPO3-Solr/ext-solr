<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Typo3PageIndexer;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\MiddlewareDispatcher;
use TYPO3\CMS\Core\Http\MiddlewareStackResolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request as ExtbaseRequest;
use TYPO3\CMS\Extbase\Mvc\Web\Response;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Http\RequestHandler;
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
        $existingAttributes = $GLOBALS['TYPO3_REQUEST'] ? $GLOBALS['TYPO3_REQUEST']->getAttributes() : [];
        foreach ($importPageIds as $importPageId) {
            $fakeTSFE = $this->getConfiguredTSFE($importPageId);
            $GLOBALS['TSFE'] = $fakeTSFE;
            $fakeTSFE->newCObj();

            if(Util::getIsTYPO3VersionBelow10()) {
                $fakeTSFE->preparePageContentGeneration();
                PageGenerator::renderContent();
            } else {
                    /** @var ServerRequestFactory $serverRequestFactory */
                $serverRequestFactory = GeneralUtility::makeInstance(ServerRequestFactory::class);
                $request = $serverRequestFactory::fromGlobals();

                    /** @var RequestHandler $requestHandler */
                $requestHandler = GeneralUtility::makeInstance(RequestHandler::class);
                $requestHandler->handle($request);
            }

            /** @var $pageIndexer \ApacheSolrForTypo3\Solr\Typo3PageIndexer */
            $pageIndexer = GeneralUtility::makeInstance(Typo3PageIndexer::class, $fakeTSFE);
            $pageIndexer->indexPage();
        }

        /** @var $beUser  \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $beUser;
        if (!empty($existingAttributes)) {
            foreach ($existingAttributes as $attributeName => $attribute) {
                $GLOBALS['TYPO3_REQUEST'] = $GLOBALS['TYPO3_REQUEST']->withAttribute($attributeName, $attribute);
            }
        }
        $this->waitToBeVisibleInSolr();
    }

    /**
     * @param string $controllerName
     * @param string $actionName
     * @param string $plugin
     * @return ExtbaseRequest
     */
    protected function getPreparedRequest($controllerName = 'Search', $actionName = 'results', $plugin = 'pi_result')
    {
        /** @var ExtbaseRequest $request */
        $request = $this->objectManager->get(ExtbaseRequest::class);
        $request->setControllerName($controllerName);
        $request->setControllerActionName($actionName);

        //@todo can be dropped when TYPO3 9 support will be dropped
        if(Util::getIsTYPO3VersionBelow10()) {
            $request->setControllerVendorName('ApacheSolrForTypo3');
        }

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