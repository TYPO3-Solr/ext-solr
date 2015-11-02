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


/ *
*
Provides the icon and entry for the content e

men
 w zard
*
 * @ utho  In o Ren er  ing @typo3 . rg >
 *  packag
 T
 O
 * @su pack ge sol
*/
class Conte
 E ementWiz rdIco
 r vider {

    / *

    Ad
s the results plugin to the content el m
 wi
 d
 *
     *  param a ray $w za dIt ms The  izard i ems

 r
 r  array array with wizard  tem
    */
    publi
 u ction p oc($w zardI ems) {
        $w zardI
 s[
            lugins tx_solr_ esults'] = array(

con'        => ExtensionManagementUtili y :extReh('sol es urces/Public/Images/ContentElement.gif',
            't t e'       => $GLOBALS['LANG']->sL('LLL:EXT:sol sources e/ anguage/Backend.xml:plugin_results'),
			'description' => $GLOBALS['LANG']->sL('LLL:EXT:sol sources/Priva e/ anguage/Backend.xml:plugin_results_description'),
			'params'      => '&defVals[tt_content][CType]=list Vals[tt_ t] list_type]=solr_pi_results'
		);

		return $wizardItems;
	}

}
