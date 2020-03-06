<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Highlight;

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


/**
 * Provides highlighting of the search words on the document's actual page by
 * adding parameters to a document's URL property.
 *
 * Initial code from ApacheSolrForTypo3\Solr\ResultDocumentModifier\SiteHighlighter
 *
 * @author Stefan Sprenger <stefan.sprenger@dkd.de>
 * @author Timo Hund <timo.hund@dkd.de>
 */

use ApacheSolrForTypo3\Solr\System\Url\UrlHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SiteHighlighterUrlModifier
 */
class SiteHighlighterUrlModifier {

    /**
     * @param string $url
     * @param string $searchWords
     * @param boolean $addNoCache
     * @param boolean $keepCHash
     * @return string
     */
    public function modify($url, $searchWords, $addNoCache = true, $keepCHash = false) {
        $searchWords = str_replace('&quot;', '', $searchWords);
        $searchWords = GeneralUtility::trimExplode(' ', $searchWords, true);

            /** @var UrlHelper $urlHelper */
        $urlHelper = GeneralUtility::makeInstance(UrlHelper::class, /** @scrutinizer ignore-type */ $url);
        $urlHelper->addQueryParameter('sword_list', $searchWords);

        if ($addNoCache) {
            $urlHelper->addQueryParameter('no_cache', '1');
        }

        if (!$keepCHash) {
            $urlHelper->removeQueryParameter('cHash');
        }

        return $urlHelper->getUrl();
    }
}
