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

namespace ApacheSolrForTypo3\Solr\ContentObject;

use ApacheSolrForTypo3\Solr\HtmlContentExtractor;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * A content object (cObj) to clean a database field in a way so that it can be
 * used to fill a Solr document's content field.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Content extends AbstractContentObject
{
    const CONTENT_OBJECT_NAME = 'SOLR_CONTENT';

    /**
     * Executes the SOLR_CONTENT content object.
     *
     * Cleans content coming from a database field, removing HTML tags ...
     *
     * @inheritDoc
     */
    public function render($conf = [])
    {
        $contentExtractor = GeneralUtility::makeInstance(
            HtmlContentExtractor::class,
            /** @scrutinizer ignore-type */ $this->getRawContent($this->cObj, $conf)
        );

        return $contentExtractor->getIndexableContent();
    }

    /**
     * Gets the raw content as configured - a certain value or database field.
     *
     * @param ContentObjectRenderer $contentObject The original content object
     * @param array $configuration content object configuration
     * @return string The raw content
     */
    protected function getRawContent($contentObject, $configuration)
    {
        $content = '';
        if (isset($configuration['value'])) {
            $content = $configuration['value'];
            unset($configuration['value']);
        }

        if (!empty($configuration)) {
            $content = $contentObject->stdWrap($content, $configuration);
        }

        return $content;
    }
}
