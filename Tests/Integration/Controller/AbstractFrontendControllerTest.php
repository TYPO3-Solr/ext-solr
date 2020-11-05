<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Typo3PageIndexer;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request as ExtbaseRequest;
use TYPO3\CMS\Extbase\Mvc\Response;
use TYPO3\CMS\Frontend\Http\RequestHandler;

/**
 * Abstract frontend controller test class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractFrontendControllerTest extends IntegrationTest
{

    /**
     * @throws NoSuchCacheException
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
            /** @var ServerRequestFactory $serverRequestFactory */
            $serverRequestFactory = GeneralUtility::makeInstance(ServerRequestFactory::class);
            $request = $serverRequestFactory::fromGlobals();

            /** @var RequestHandler $requestHandler */
            $requestHandler = GeneralUtility::makeInstance(RequestHandler::class);
            $requestHandler->handle($request);

            /** @var Typo3PageIndexer $pageIndexer */
            $pageIndexer = GeneralUtility::makeInstance(Typo3PageIndexer::class, $fakeTSFE);
            $pageIndexer->indexPage();
        }

        /** @var BackendUserAuthentication $beUser */
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

        $request->setPluginName($plugin);
        $request->setFormat('html');
        $request->setControllerExtensionName('Solr');

        return $request;
    }

    /**
     * @return Response
     */
    protected function getPreparedResponse(): Response
    {
        /** @var Response $response */
        return $this->objectManager->get(Response::class);
    }
}