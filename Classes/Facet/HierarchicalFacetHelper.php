<?php
namespace ApacheSolrForTypo3\Solr\Facet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Helper for the hierarchical menu structure.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class HierarchicalFacetHelper
{

    /**
     * Parent content object, set when called by ContentObjectRenderer->callUserFunction()
     *
     * @var ContentObjectRenderer
     */
    public $cObj;


    /**
     * Builds a menu structure usable with HMENU and returns it.
     *
     * Starts with the top level menu entries and hands the sub menu building
     * off to a recursive method.
     *
     * @param string $content
     * @param array $configuration
     * @return array A menu structure usable for HMENU
     */
    public function getMenuStructure($content, array $configuration)
    {
        $menuStructure = array();
        $facetOptions = $this->cObj->data['facetOptions'];

        foreach ($facetOptions as $facetOptionKey => $facetOption) {
            // let's start with top level menu options
            if (substr($facetOptionKey, 0, 1) == '0') {
                $topLevelMenu = array(
                    'title' => $this->getFacetOptionLabel($facetOptionKey,
                        $facetOption['numberOfResults']),
                    'facetKey' => HierarchicalFacetRenderer::getLastPathSegmentFromHierarchicalFacetOption($facetOptionKey),
                    'numberOfResults' => $facetOption['numberOfResults'],
                    '_OVERRIDE_HREF' => $facetOption['url'],
                    'ITEM_STATE' => $facetOption['selected'] ? 'ACT' : 'NO',
                    '_PAGES_OVERLAY' => ($GLOBALS['TSFE']->sys_language_uid > 0)
                );

                list(, $mainMenuName) = explode('-', $facetOptionKey, 2);

                // build sub menus recursively
                $subMenu = $this->getSubMenu($facetOptions, $mainMenuName, 1);
                if (!empty($subMenu)) {
                    $topLevelMenu['_SUB_MENU'] = $subMenu;
                    if ($topLevelMenu['ITEM_STATE'] == 'ACT') {
                        $topLevelMenu['ITEM_STATE'] = 'ACTIFSUB';
                    } else {
                        $topLevelMenu['ITEM_STATE'] = 'IFSUB';
                    }
                }

                $menuStructure[] = $topLevelMenu;
            }
        }

        return $menuStructure;
    }

    /**
     * Generates a facet option label from the given facet option.
     *
     * @param string $facetOptionKey A hierachical facet option path
     * @param integer $facetOptionResultCount
     * @return string The label for the facet option consisting of the last part of the path and the options result count
     */
    protected function getFacetOptionLabel(
        $facetOptionKey,
        $facetOptionResultCount
    ) {
        // use the last path segment and the result count to build the label
        $lastPathSegment = HierarchicalFacetRenderer::getLastPathSegmentFromHierarchicalFacetOption($facetOptionKey);
        $facetOptionLabel = $lastPathSegment . ' (' . $facetOptionResultCount . ')';

        return $facetOptionLabel;
    }

    /**
     * Recursively builds a sub menu structure for the current menu.
     *
     * @param array $facetOptions Array of facet options
     * @param string $menuName Name of the top level menu to build the sub menu structure for
     * @param integer $level The sub level depth
     * @return array Returns an array sub menu structure if a sub menu exists, an empty array otherwise
     */
    protected function getSubMenu(array $facetOptions, $menuName, $level)
    {
        $menu = array();

        $subMenuEntryPrefix = $level . '-' . $menuName . '/';

        foreach ($facetOptions as $facetOptionKey => $facetOption) {
            // find the sub menu items for the current menu
            if (GeneralUtility::isFirstPartOfStr($facetOptionKey,
                $subMenuEntryPrefix)
            ) {
                $currentMenu = array(
                    'title' => $this->getFacetOptionLabel($facetOptionKey,
                        $facetOption['numberOfResults']),
                    'facetKey' => HierarchicalFacetRenderer::getLastPathSegmentFromHierarchicalFacetOption($facetOptionKey),
                    'numberOfResults' => $facetOption['numberOfResults'],
                    '_OVERRIDE_HREF' => $facetOption['url'],
                    'ITEM_STATE' => $facetOption['selected'] ? 'ACT' : 'NO',
                    '_PAGES_OVERLAY' => ($GLOBALS['TSFE']->sys_language_uid > 0)
                );

                $lastPathSegment = HierarchicalFacetRenderer::getLastPathSegmentFromHierarchicalFacetOption($facetOptionKey);

                // move one level down (recursion)
                $subMenu = $this->getSubMenu(
                    $facetOptions,
                    $menuName . '/' . $lastPathSegment,
                    $level + 1
                );
                if (!empty($subMenu)) {
                    $currentMenu['_SUB_MENU'] = $subMenu;
                    if ($currentMenu['ITEM_STATE'] == 'ACT') {
                        $currentMenu['ITEM_STATE'] = 'ACTIFSUB';
                    } else {
                        $currentMenu['ITEM_STATE'] = 'IFSUB';
                    }
                }

                $menu[] = $currentMenu;
            }
        }

        // return one level up
        return $menu;
    }
}
