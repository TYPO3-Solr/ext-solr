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

namespace ApacheSolrForTypo3\Solr;

use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
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
     * @param Document $pageDocument the generated page document
     * @param TypoScriptFrontendController $page the page object with information about page id or language
     */
    public function postProcessPageDocument(Document $pageDocument, TypoScriptFrontendController $page);
}
