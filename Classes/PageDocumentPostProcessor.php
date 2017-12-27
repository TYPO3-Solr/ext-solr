<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014-2015 Steffen Ritter <steffen.ritter@typo3.org>
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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Page document post processor interface to handle page documents after they
 * have been put together, but not yet submitted to Solr.
 *
 * @author Steffen Ritter <steffen.ritter@typo3.org>
 */
interface PageDocumentPostProcessor
{

    /**
     * Allows Modification of the PageDocument
     * Can be used to trigger actions when all contextual variables of the pageDocument to be indexed are known
     *
     * @param \Apache_Solr_Document $pageDocument the generated page document
     * @param TypoScriptFrontendController $page the page object with information about page id or language
     * @return void
     */
    public function postProcessPageDocument(
        \Apache_Solr_Document $pageDocument,
        TypoScriptFrontendController $page
    );
}
