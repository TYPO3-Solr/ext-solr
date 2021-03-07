<?php
declare(strict_types=1);

namespace ApacheSolrForTypo3\Solr\Middleware;

/***************************************************************
 * Copyright notice
 *
 * (c) 2020 dkd Internet Services GmbH <solr-eb-support@dkd.de>
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the text file GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\AuthorizationService;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequestHandler;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Util;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Class FrontendUserAuthenticator is responsible to fake a frontend user and fe_groups.
 * It makes possible to Index access restricted pages and content elements.
 */
class FrontendUserAuthenticator implements MiddlewareInterface
{

    /**
     * @var Context
     */
    protected $context;

    /**
     * FrontendUserAuthorizator constructor.
     *
     * @param Context|null $context
     * @noinspection PhpUnused
     */
    public function __construct(?Context $context = null)
    {
        $this->context = $context ?? GeneralUtility::makeInstance(Context::class);
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @noinspection PhpUnused
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request->hasHeader(PageIndexerRequest::SOLR_INDEX_HEADER)) {
            return $handler->handle($request);
        }

        // disable TSFE cache for TYPO3 v10
        $request = $request->withAttribute('noCache', true);
        $jsonEncodedParameters = $request->getHeader(PageIndexerRequest::SOLR_INDEX_HEADER)[0];

        /* @var PageIndexerRequestHandler $pageIndexerRequestHandler */
        $pageIndexerRequestHandler = GeneralUtility::makeInstance(PageIndexerRequestHandler::class, $jsonEncodedParameters);
        if (!$pageIndexerRequestHandler->getRequest()->isAuthenticated()) {
            /* @var SolrLogManager $logger */
            $logger = GeneralUtility::makeInstance(SolrLogManager::class, self::class);
            $logger->log(
                SolrLogManager::ERROR,
                'Invalid Index Queue Frontend Request detected!',
                [
                    'page indexer request' => (array)$pageIndexerRequestHandler->getRequest(),
                    'index queue header' => $jsonEncodedParameters
                ]
            );

            return new JsonResponse(['error' => ['code' => 403, 'message' => 'Invalid Index Queue Request.']], 403);

        }
        $request = $this->tryToAuthenticateFrontendUser($pageIndexerRequestHandler, $request);

        return $handler->handle($request);
    }

    /**
     * Fakes a logged in user to retrieve access restricted content.
     *
     * @param PageIndexerRequestHandler $handler
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     * @noinspection PhpUnused
     */
    protected function tryToAuthenticateFrontendUser(PageIndexerRequestHandler $handler, ServerRequestInterface $request): ServerRequestInterface
    {
        $accessRootline = $this->getAccessRootline($handler);
        $stringAccessRootline = (string)$accessRootline;

        if (empty($stringAccessRootline)) {
            return $request;
        }

        $groups = $accessRootline->getGroups();

        /* @var FrontendUserAuthentication $feUser */
        $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $feUser->user[$feUser->username_column] = AuthorizationService::SOLR_INDEXER_USERNAME;
        /* @noinspection PhpParamsInspection */
        $this->context->setAspect('frontend.user', GeneralUtility::makeInstance(UserAspect::class, $feUser, $groups));
        $request = $request->withAttribute('frontend.user', $feUser);

        return $request;
    }

    /**
     * Gets the access rootline as defined by the request.
     *
     * @param PageIndexerRequestHandler $handler
     * @return Rootline The access rootline to use for indexing.
     */
    protected function getAccessRootline(PageIndexerRequestHandler $handler): Rootline
    {
        $stringAccessRootline = '';

        if ($handler->getRequest()->getParameter('accessRootline')) {
            $stringAccessRootline = $handler->getRequest()->getParameter('accessRootline');
        }

        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return GeneralUtility::makeInstance(Rootline::class, /** @scrutinizer ignore-type */ $stringAccessRootline);
    }

}
