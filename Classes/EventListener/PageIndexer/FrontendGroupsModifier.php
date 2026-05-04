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
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingInstructions;
use ApacheSolrForTypo3\Solr\Middleware\AuthorizationService;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\ModifyResolvedFrontendGroupsEvent;

/**
 * Fakes fe_group membership during indexing sub-requests to allow
 * indexing of access-restricted pages and content elements.
 */
final readonly class FrontendGroupsModifier
{
    #[AsEventListener(
        identifier: 'solr.index.PageIndexer.FrontendUserAuthenticator',
    )]
    public function __invoke(ModifyResolvedFrontendGroupsEvent $event): void
    {
        $instructions = $event->getRequest()->getAttribute('solr.indexingInstructions');
        if (!$instructions instanceof IndexingInstructions) {
            return;
        }

        $isFindUserGroups = $instructions->isFindUserGroups();
        $isIndexPage = $instructions->isPageIndexing();

        if (!$isFindUserGroups && !$isIndexPage) {
            return;
        }

        // For findUserGroups: grant access to the page by faking membership in the page's user group.
        // This allows the UserGroupDetector to render the page and detect all content fe_groups.
        if ($isFindUserGroups) {
            $pageFeGroup = (int)($instructions->getParameter('pageUserGroup') ?? 0);

            if ($pageFeGroup > 0) {
                $groupData = [
                    [
                        'title' => 'group_(' . $pageFeGroup . ')',
                        'uid' => $pageFeGroup,
                        'pid' => 0,
                    ],
                ];
                $event->getUser()->user[$event->getUser()->username_column] = AuthorizationService::SOLR_INDEXER_USERNAME;
                $event->setGroups($groupData);
            }
            return;
        }

        // For indexPage: use the access rootline to determine required groups
        $groups = $this->resolveFrontendUserGroups($instructions);

        $noRelevantFrontendUserGroupResolved = empty($groups) || (count($groups) === 1 && $groups[0] === 0);
        if ($instructions->getUserGroup() === 0
            && (
                (int)($instructions->getParameter('pageUserGroup') ?? 0) !== -2
                && (int)($instructions->getParameter('pageUserGroup') ?? 0) < 1
            )
            && $noRelevantFrontendUserGroupResolved
        ) {
            return;
        }

        if ($instructions->getUserGroup() > 0
            && (int)($instructions->getParameter('pageUserGroup') ?? 0) > 0
        ) {
            $groups[] = (int)$instructions->getParameter('pageUserGroup');
        }
        $groupData = [];
        foreach ($groups as $groupUid) {
            if (in_array($groupUid, [-2, -1])) {
                continue;
            }
            $groupData[] = [
                'title' => 'group_(' . $groupUid . ')',
                'uid' => $groupUid,
                'pid' => 0,
            ];
        }
        $event->getUser()->user[$event->getUser()->username_column] = AuthorizationService::SOLR_INDEXER_USERNAME;
        $event->setGroups($groupData);
    }

    private function resolveFrontendUserGroups(IndexingInstructions $instructions): array
    {
        $accessRootline = $this->getAccessRootline($instructions);
        $stringAccessRootline = (string)$accessRootline;
        if (empty($stringAccessRootline)) {
            return [];
        }
        return $accessRootline->getGroups();
    }

    private function getAccessRootline(IndexingInstructions $instructions): Rootline
    {
        $stringAccessRootline = $instructions->getAccessRootline();
        return GeneralUtility::makeInstance(Rootline::class, $stringAccessRootline);
    }
}
