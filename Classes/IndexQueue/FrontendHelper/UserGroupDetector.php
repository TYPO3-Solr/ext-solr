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

namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Domain\Repository\PageRepositoryGetPageHookInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepositoryGetPageOverlayHookInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectPostInitHookInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * The UserGroupDetector is responsible to identify the fe_group references on records that are visible on the page (not the page itself).
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class UserGroupDetector implements
    FrontendHelper,
    SingletonInterface,
    ContentObjectPostInitHookInterface,
    PageRepositoryGetPageHookInterface,
    PageRepositoryGetPageOverlayHookInterface
{
    /**
     * Index Queue page indexer request.
     */
    protected ?PageIndexerRequest $request = null;

    /**
     * This frontend helper's executed action.
     */
    protected string $action = 'findUserGroups';

    /**
     * Holds the original, unmodified TCA during user group detection
     */
    protected array $originalTca;

    /**
     * Collects the usergroups used on a page.
     */
    protected array $frontendGroups = [];

    protected ?SolrLogManager $logger = null;
    // activation

    /**
     * Activates a frontend helper by registering for hooks and other
     * resources required by the frontend helper to work.
     */
    public function activate(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['configArrayPostProc'][__CLASS__] = UserGroupDetector::class . '->deactivateTcaFrontendGroupEnableFields';
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['hook_checkEnableFields'][__CLASS__] = UserGroupDetector::class . '->checkEnableFields';

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPage'][__CLASS__] = UserGroupDetector::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPageOverlay'][__CLASS__] = UserGroupDetector::class;

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['postInit'][__CLASS__] = UserGroupDetector::class;
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
    }

    /**
     * Disables the group access check by resetting the fe_group field in the given page table row.
     * Will be called by the hook in the TypoScriptFrontendController in the checkEnableFields() method.
     * @see TypoScriptFrontendController::checkEnableFields
     *
     * @noinspection PhpUnusedParameterInspection
     *               PhpUnused used in {@link self::activate()} "hook_checkEnableFields"
     */
    public function checkEnableFields(
        array &$parameters,
        TypoScriptFrontendController $tsfe
    ): void {
        $parameters['row']['fe_group'] = '';
    }

    /**
     * Deactivates the frontend user group fields in TCA so that no access
     * restrictions apply during page rendering.
     *
     * @noinspection PhpUnusedParameterInspection
     *               PhpUnused used in {@link self::activate()} "configArrayPostProc"
     */
    public function deactivateTcaFrontendGroupEnableFields(
        array &$parameters,
        TypoScriptFrontendController $parentObject
    ): void {
        $this->originalTca = $GLOBALS['TCA'];

        foreach ($GLOBALS['TCA'] as $tableName => $tableConfiguration) {
            if (isset($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns']['fe_group'])) {
                unset($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns']['fe_group']);
            }
        }
    }

    // manipulation

    /**
     * Modifies the database query parameters so that access checks for pages
     * are not performed any longer.
     *
     * @param int $uid The page ID
     * @param bool $disableGroupAccessCheck If set, the check for group access is disabled. VERY rarely used
     * @param PageRepository $parentObject parent \TYPO3\CMS\Core\Domain\Repository\PageRepository object
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getPage_preProcess(
        &$uid,
        &$disableGroupAccessCheck,
        PageRepository $parentObject
    ) {
        $disableGroupAccessCheck = true;
        // @todo: Check if reset "where_groupAccess" really wanted. Most probably the core aspect 'frontend.user' must be used instead.
        $parentObject->where_groupAccess = ''; // just to be on the safe side
    }

    /**
     * Modifies page records so that when checking for access through fe groups
     * no groups or extendToSubpages flag is found and thus access is granted.
     *
     * @param array $pageInput Page record
     * @param int $lUid Overlay language ID
     * @param PageRepository $parent Parent \TYPO3\CMS\Core\Domain\Repository\PageRepository object
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getPageOverlay_preProcess(
        &$pageInput,
        &$lUid,
        PageRepository $parent
    ) {
        if (!is_array($pageInput)) {
            return;
        }
        $pageInput['fe_group'] = '';
        $pageInput['extendToSubpages'] = '0';
    }

    // execution

    /**
     * Hook for post-processing the initialization of ContentObjectRenderer
     *
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     *               PhpMissingReturnTypeInspection
     */
    public function postProcessContentObjectInitialization(ContentObjectRenderer &$parentObject)
    {
        $this->request = $parentObject->getRequest()->getAttribute('solr.pageIndexingInstructions');
        if (!empty($parentObject->currentRecord)) {
            [$table] = explode(':', $parentObject->currentRecord);

            if (!empty($table) && $table != 'pages') {
                $this->findFrontendGroups($parentObject->data, $table);
            }
        }
    }

    /**
     * Tracks user groups access restriction applied to the records.
     *
     * @param array $record A record as an array of fieldname => fieldvalue mappings
     * @param string $table Table name the record belongs to
     */
    protected function findFrontendGroups(array $record, string $table): void
    {
        if (isset($this->originalTca[$table]['ctrl']['enablecolumns']['fe_group'])) {
            $frontendGroups = $record[$this->originalTca[$table]['ctrl']['enablecolumns']['fe_group']] ?? null;

            if (empty($frontendGroups)) {
                // default = public access
                $frontendGroups = 0;
            } elseif ($this->request->getParameter('loggingEnabled')) {
                $this->logger->info(
                    'Access restriction found',
                    [
                        'groups' => $frontendGroups,
                        'record' => $record,
                        'record type' => $table,
                    ]
                );
            }

            $this->frontendGroups[] = $frontendGroups;
        }
    }

    /**
     * Returns an array of user groups that have been tracked during page rendering.
     */
    protected function getFrontendGroups(): array
    {
        $frontendGroupsList = implode(',', $this->frontendGroups);
        $frontendGroups = GeneralUtility::intExplode(
            ',',
            $frontendGroupsList,
            true
        );

        // clean up: filter double groups
        $frontendGroups = array_unique($frontendGroups);
        $frontendGroups = array_values($frontendGroups);

        if (empty($frontendGroups)) {
            // most likely an empty page with no content elements => public
            $frontendGroups[] = 0;
        }

        // Index user groups first
        sort($frontendGroups, SORT_NUMERIC);
        return array_reverse($frontendGroups);
    }

    /**
     * Adds the user groups found to the PageIndexerResponse
     */
    public function deactivate(PageIndexerResponse $response): void
    {
        $response->addActionResult($this->action, $this->getFrontendGroups());
    }
}
