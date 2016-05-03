<?php
namespace ApacheSolrForTypo3\Solr\Backend;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014-2015 Ingo Renner <ingo@typo3.org>
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

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Provides the icon and entry for the content element wizard
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class ContentElementWizardIconProvider
{

    /**
     * Adds the results plugin to the content element wizard
     *
     * @param array $wizardItems The wizard items
     * @return array array with wizard items
     */
    public function proc($wizardItems)
    {
        $wizardItems['plugins_tx_solr_results'] = array(
            'icon' => ExtensionManagementUtility::extRelPath('solr') . 'Resources/Public/Images/ContentElement.gif',
            'title' => $GLOBALS['LANG']->sL('LLL:EXT:solr/Resources/Private/Language/locallang.xlf:plugin_results'),
            'description' => $GLOBALS['LANG']->sL('LLL:EXT:solr/Resources/Private/Language/locallang.xlf:plugin_results_description'),
            'params' => '&defVals[tt_content][CType]=list&defVals[tt_content][list_type]=solr_pi_results'
        );

        return $wizardItems;
    }
}
