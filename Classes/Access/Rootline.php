<?php

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

namespace ApacheSolrForTypo3\Solr\Access;

use RuntimeException;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * "Access Rootline", represents all pages and specifically those setting
 * frontend user group access restrictions in a page's rootline.
 *
 * The access rootline only contains pages which set frontend user access
 * restrictions and extend them to sub-pages. The format is as follows:
 *
 * pageId1:group1,group2/pageId2:group3/c:group1,group4,groupN
 *
 * The single elements of the access rootline are separated by a slash
 * character. All but the last elements represent pages, the last element
 * defines the access restrictions applied to the page's content elements
 * and records shown on the page.
 * Each page element is composed by the page ID of the page setting frontend
 * user access restrictions, a colon, and a comma separated list of frontend
 * user group IDs restricting access to the page.
 * The content access element does not have a page ID, instead it replaces
 * the ID by a lower case C.
 *
 * The groups for page elements are compared using OR, so the user needs to be
 * a member of only one of the groups listed for a page. The elements are
 * checked combined using AND, so the user must be member of at least one
 * group in each page element. However, the groups in the content access
 * element are checked using AND. So the user must be member of all the groups
 * listed in the content access element to see the document.
 *
 * An access rootline for a generic record could instead be short like this:
 *
 * r:group1,group2,groupN
 *
 * In this case the lower case R tells us that we're dealing with a record
 * like tt_news or the like. For records the groups are checked using OR
 * instead of using AND as it would be the case with content elements.
 */
class Rootline
{
    /**
     * Delimiter for page and content access right elements in the rootline.
     */
    public const ELEMENT_DELIMITER = '/';

    /**
     * Storage for access rootline elements
     */
    protected array $rootlineElements = [];

    /**
     * Constructor, turns a string representation of an access rootline into an
     * object representation.
     *
     * @param string|null $accessRootline Access Rootline String representation.
     */
    public function __construct(?string $accessRootline = null)
    {
        if (!is_null($accessRootline)) {
            $rawRootlineElements = explode(self::ELEMENT_DELIMITER, $accessRootline);
            foreach ($rawRootlineElements as $rawRootlineElement) {
                try {
                    $this->push(GeneralUtility::makeInstance(RootlineElement::class, $rawRootlineElement));
                } catch (RootlineElementFormatException) {
                    // just ignore the faulty element for now, might log this later
                }
            }
        }
    }

    /**
     * Adds an Access Rootline Element to the end of the rootline.
     *
     * @param RootlineElement $rootlineElement Element to add.
     */
    public function push(RootlineElement $rootlineElement): void
    {
        $lastElementIndex = max(0, (count($this->rootlineElements) - 1));

        if (!empty($this->rootlineElements[$lastElementIndex])) {
            if ($this->rootlineElements[$lastElementIndex]->getType() == RootlineElement::ELEMENT_TYPE_CONTENT) {
                throw new RootlineElementFormatException(
                    'Can not add an element to an Access Rootline whose\' last element is a content type element.',
                    1294422132,
                );
            }

            if ($this->rootlineElements[$lastElementIndex]->getType() == RootlineElement::ELEMENT_TYPE_RECORD) {
                throw new RootlineElementFormatException(
                    'Can not add an element to an Access Rootline whose\' last element is a record type element.',
                    1308343423,
                );
            }
        }

        $this->rootlineElements[] = $rootlineElement;
    }

    /**
     * Gets the Access Rootline for a specific page id.
     *
     * @param int $pageId The page id to generate the Access Rootline for.
     * @param string $mountPointParameter The mount point parameter for generating the rootline.
     * @return Rootline Access Rootline for the given page id.
     */
    public static function getAccessRootlineByPageId(
        int $pageId,
        string $mountPointParameter = '',
    ): Rootline {
        /** @var Rootline $accessRootline */
        $accessRootline = GeneralUtility::makeInstance(Rootline::class);
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageId, $mountPointParameter);
        try {
            $rootline = $rootlineUtility->get();
        } catch (RuntimeException) {
            $rootline = [];
        }
        $rootline = array_reverse($rootline);
        // parent pages
        foreach ($rootline as $pageRecord) {
            if ($pageRecord['fe_group']
                && $pageRecord['extendToSubpages']
                && $pageRecord['uid'] != $pageId
            ) {
                $accessRootline->push(GeneralUtility::makeInstance(
                    RootlineElement::class,
                    $pageRecord['uid'] . RootlineElement::PAGE_ID_GROUP_DELIMITER . $pageRecord['fe_group'],
                ));
            }
        }

        /** @var PageRepository $pageSelector */
        $pageSelector = GeneralUtility::makeInstance(PageRepository::class);

        // current page
        $currentPageRecord = $pageSelector->getPage($pageId, true);
        if ($currentPageRecord['fe_group']) {
            $accessRootline->push(GeneralUtility::makeInstance(
                RootlineElement::class,
                $currentPageRecord['uid'] . RootlineElement::PAGE_ID_GROUP_DELIMITER . $currentPageRecord['fe_group'],
            ));
        }

        return $accessRootline;
    }

    /**
     * Returns the string representation of the access rootline.
     *
     * @return string String representation of the access rootline.
     */
    public function __toString()
    {
        $stringElements = [];

        foreach ($this->rootlineElements as $rootlineElement) {
            $stringElements[] = (string)$rootlineElement;
        }

        return implode(self::ELEMENT_DELIMITER, $stringElements);
    }

    /**
     * Gets the groups in the Access Rootline.
     *
     * @return array An array of sorted, unique user group IDs required to access a page.
     */
    public function getGroups(): array
    {
        $groups = [];

        foreach ($this->rootlineElements as $rootlineElement) {
            $rootlineElementGroups = $rootlineElement->getGroups();
            $groups = array_merge($groups, $rootlineElementGroups);
        }

        return $this->cleanGroupArray($groups);
    }

    /**
     * Cleans an array of frontend user group IDs. Removes the duplicates and sorts
     * the array.
     *
     * @param array $groups An array of frontend user group IDs
     * @return array An array of cleaned frontend user group IDs, unique, sorted.
     */
    public static function cleanGroupArray(array $groups): array
    {
        $groups = array_unique($groups); // removes duplicates
        sort($groups, SORT_NUMERIC); // sort

        return $groups;
    }
}
