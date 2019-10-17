<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectPostInitHookInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Frontend\Page\PageRepositoryGetPageHookInterface;
use TYPO3\CMS\Frontend\Page\PageRepositoryGetPageOverlayHookInterface;

/**
 * The UserGroupDetector is responsible to identify the fe_group references on records that are visible on the page (not the page itself).
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class UserGroupDetector extends AbstractFrontendHelper implements
    SingletonInterface,
    ContentObjectPostInitHookInterface,
    PageRepositoryGetPageHookInterface,
    PageRepositoryGetPageOverlayHookInterface
{

    /**
     * This frontend helper's executed action.
     */
    protected $action = 'findUserGroups';

    /**
     * Holds the original, unmodified TCA during user group detection
     *
     * @var array
     */
    protected $originalTca = null;

    /**
     * Collects the usergroups used on a page.
     *
     * @var array
     */
    protected $frontendGroups = [];

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager
     */
    protected $logger = null;

    // activation

    /**
     * Activates a frontend helper by registering for hooks and other
     * resources required by the frontend helper to work.
     */
    public function activate()
    {
        // register hooks
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['isOutputting'][__CLASS__] = UserGroupDetector::class . '->disableFrontendOutput';
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['tslib_fe-PostProc'][__CLASS__] = UserGroupDetector::class . '->disableCaching';
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['configArrayPostProc'][__CLASS__] = UserGroupDetector::class . '->deactivateTcaFrontendGroupEnableFields';
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['hook_checkEnableFields'][__CLASS__] = UserGroupDetector::class . '->checkEnableFields';

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPage'][__CLASS__] = UserGroupDetector::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPageOverlay'][__CLASS__] = UserGroupDetector::class;

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['postInit'][__CLASS__] = UserGroupDetector::class;
    }

    /**
     * Disables the group access check by resetting the fe_group field in the given page table row.
     * Will be called by the hook in the TypoScriptFrontendController in the checkEnableFields() method.
     *
     * @param array $parameters
     * @param TypoScriptFrontendController $tsfe
     * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::checkEnableFields()
     */
    public function checkEnableFields(
        $parameters,
        /** @noinspection PhpUnusedParameterInspection */
        $tsfe
    ) {
        $parameters['row']['fe_group'] = '';
    }

    /**
     * Deactivates the frontend user group fields in TCA so that no access
     * restrictions apply during page rendering.
     *
     * @param array $parameters Parameters from frontend
     * @param TypoScriptFrontendController $parentObject TSFE object
     */
    public function deactivateTcaFrontendGroupEnableFields(
        /** @noinspection PhpUnusedParameterInspection */
        &$parameters,
        /** @noinspection PhpUnusedParameterInspection */
        $parentObject
    ) {
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
     * @param PageRepository $parentObject parent \TYPO3\CMS\Frontend\Page\PageRepository object
     */
    public function getPage_preProcess(
        &$uid,
        &$disableGroupAccessCheck,
        PageRepository $parentObject
    ) {
        $disableGroupAccessCheck = true;
        $parentObject->where_groupAccess = ''; // just to be on the safe side
    }

    /**
     * Modifies page records so that when checking for access through fe groups
     * no groups or extendToSubpages flag is found and thus access is granted.
     *
     * @param array $pageRecord Page record
     * @param int $languageUid Overlay language ID
     * @param PageRepository $parentObject Parent \TYPO3\CMS\Frontend\Page\PageRepository object
     */
    public function getPageOverlay_preProcess(
        &$pageRecord,
        &$languageUid,
        PageRepository $parentObject
    ) {
        if (is_array($pageRecord)) {
            $pageRecord['fe_group'] = '';
            $pageRecord['extendToSubpages'] = '0';
        }
    }

    // execution

    /**
     * Hook for post processing the initialization of ContentObjectRenderer
     *
     * @param ContentObjectRenderer $parentObject parent content object
     */
    public function postProcessContentObjectInitialization(ContentObjectRenderer &$parentObject)
    {
        if (!empty($parentObject->currentRecord)) {
            list($table) = explode(':', $parentObject->currentRecord);

            if (!empty($table) && $table != 'pages') {
                $this->findFrontendGroups($parentObject->data, $table);
            }
        }
    }

    /**
     * Tracks user groups access restriction applied to records.
     *
     * @param array $record A record as an array of fieldname => fieldvalue mappings
     * @param string $table Table name the record belongs to
     */
    protected function findFrontendGroups($record, $table)
    {
        if ($this->originalTca[$table]['ctrl']['enablecolumns']['fe_group']) {
            $frontendGroups = $record[$this->originalTca[$table]['ctrl']['enablecolumns']['fe_group']];

            if (empty($frontendGroups)) {
                // default = public access
                $frontendGroups = 0;
            } else {
                if ($this->request->getParameter('loggingEnabled')) {
                    $this->logger->log(
                        SolrLogManager::INFO,
                        'Access restriction found',
                        [
                            'groups' => $frontendGroups,
                            'record' => $record,
                            'record type' => $table,
                        ]
                    );
                }
            }

            $this->frontendGroups[] = $frontendGroups;
        }
    }

    /**
     * Returns an array of user groups that have been tracked during page
     * rendering.
     *
     * @return array Array of user group IDs
     */
    protected function getFrontendGroups()
    {
        $frontendGroupsList = implode(',', $this->frontendGroups);
        $frontendGroups = GeneralUtility::trimExplode(',', $frontendGroupsList,
            true);

        // clean up: filter double groups
        $frontendGroups = array_unique($frontendGroups);
        $frontendGroups = array_values($frontendGroups);

        if (empty($frontendGroups)) {
            // most likely an empty page with no content elements => public
            $frontendGroups[] = '0';
        }

        return $frontendGroups;
    }

    /**
     * Returns the user groups found.
     *
     * @return array Array of user groups.
     */
    public function getData()
    {
        return $this->getFrontendGroups();
    }
}
