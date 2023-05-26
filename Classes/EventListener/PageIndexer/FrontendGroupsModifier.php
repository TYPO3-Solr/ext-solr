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

namespace ApacheSolrForTypo3\Solr\EventListener\PageIndexer;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\AuthorizationService;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequestHandler;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\ModifyResolvedFrontendGroupsEvent;

/**
 * Class FrontendGroupsModifier is responsible to fake the fe_groups to make
 * the indexing of access restricted pages and content elements.
 */
class FrontendGroupsModifier
{
    /**
     * Modifies the fe_groups of a user on X-Tx-Solr-Iq requests.
     *
     * @param ModifyResolvedFrontendGroupsEvent $event
     * @throws PropagateResponseException
     */
    public function __invoke(ModifyResolvedFrontendGroupsEvent $event): void
    {
        if ($event->getRequest() === null || !$event->getRequest()->hasHeader(PageIndexerRequest::SOLR_INDEX_HEADER)) {
            return;
        }

        $jsonEncodedParameters = $event->getRequest()->getHeader(PageIndexerRequest::SOLR_INDEX_HEADER)[0];
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
                    'index queue header' => $jsonEncodedParameters,
                ]
            );
            throw new PropagateResponseException(
                new JsonResponse(
                    [
                        'error' => [
                            'code' => 403,
                            'message' => 'Invalid Index Queue Request.',
                            ],
                    ],
                    403
                ),
                1646655622
            );
        }

        $groups = $this->resolveFrontendUserGroups($event->getRequest());
        $groupData = [];
        foreach ($groups as $groupUid) {
            if (in_array($groupUid, [-2, -1])) {
                continue;
            }
            $groupData[] = [
                'title' => 'group_(' . $groupUid . ')',
                'uid' => $groupUid,
                'pig' => 0,
            ];
        }
        $event->getUser()->user[$event->getUser()->username_column] = AuthorizationService::SOLR_INDEXER_USERNAME;
        $event->setGroups($groupData);
    }

    /**
     * Resolves a logged in fe_groups to retrieve access restricted content.
     *
     * @param ServerRequestInterface $request
     * @return array
     */
    protected function resolveFrontendUserGroups(ServerRequestInterface $request): array
    {
        $accessRootline = $this->getAccessRootline($request);
        $stringAccessRootline = (string)$accessRootline;
        if (empty($stringAccessRootline)) {
            return [];
        }
        return $accessRootline->getGroups();
    }

    /**
     * Gets the access rootline as defined by the request.
     *
     * @param RequestInterface $request
     * @return Rootline
     */
    protected function getAccessRootline(RequestInterface $request): Rootline
    {
        $stringAccessRootline = '';

        $jsonEncodedParameters = $request->getHeader(PageIndexerRequest::SOLR_INDEX_HEADER)[0];
        /* @var PageIndexerRequestHandler $pageIndexerRequestHandler */
        $pageIndexerRequestHandler = GeneralUtility::makeInstance(PageIndexerRequestHandler::class, $jsonEncodedParameters);

        if ($pageIndexerRequestHandler->getRequest()->getParameter('accessRootline')) {
            $stringAccessRootline = $pageIndexerRequestHandler->getRequest()->getParameter('accessRootline');
        }
        return GeneralUtility::makeInstance(Rootline::class, $stringAccessRootline);
    }
}
