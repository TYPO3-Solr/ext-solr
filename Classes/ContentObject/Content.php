<?php
namespace ApacheSolrForTypo3\Solr\ContentObject;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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
