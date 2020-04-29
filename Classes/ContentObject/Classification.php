<?php
namespace ApacheSolrForTypo3\Solr\ContentObject;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Index\Classification\Classification as ClassificationItem;
use ApacheSolrForTypo3\Solr\Domain\Index\Classification\ClassificationService;
use InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;

/**
 * A content object (cObj) to classify content based on a configuration.
 *
 * Example usage:
 *
 * keywords = SOLR_CLASSIFICATION # supports stdWrap
 * keywords {
 *   field = __solr_content # a comma separated field. instead of field you can also use "value"
 *   classes {
 *     1 {
 *        patterns = smartphone, mobile, mobilephone # list of patterns that need to match to assign that class
 *        class = mobilephone # class that should be assigned when a pattern matches
 *     }
 *   }
 * }
 */
class Classification extends AbstractContentObject
{
    const CONTENT_OBJECT_NAME = 'SOLR_CLASSIFICATION';

    /**
     * Executes the SOLR_CLASSIFICATION content object.
     *
     * Returns mapped classes when the field matches on of the configured patterns ...
     *
     * @inheritDoc
     */
    public function render($conf = [])
    {

        if (!is_array($conf['classes.'])) {
            throw new InvalidArgumentException('No class configuration configured for SOLR_CLASSIFICATION object. Given configuration: ' . serialize($conf));
        }

        $configuredMappedClasses = $conf['classes.'];
        unset($conf['classes.']);

        $data = '';
        if (isset($conf['value'])) {
            $data = $conf['value'];
            unset($conf['value']);
        }

        if (!empty($conf)) {
            $data = $this->cObj->stdWrap($data, $conf);
        }
        $classifications = $this->buildClassificationsFromConfiguration($configuredMappedClasses);
        /** @var $classificationService ClassificationService */
        $classificationService = GeneralUtility::makeInstance(ClassificationService::class);

        return serialize($classificationService->getMatchingClassNames((string)$data, $classifications));
    }

    /**
     * Builds an array of Classification objects from the passed classification configuration.
     *
     * @param array $configuredMappedClasses
     * @return ClassificationItem[]
     */
    protected function buildClassificationsFromConfiguration($configuredMappedClasses) : array
    {
        $classifications = [];
        foreach ($configuredMappedClasses as $class) {
            if ( (empty($class['patterns']) && empty($class['matchPatterns'])) || empty($class['class'])) {
                throw new InvalidArgumentException('A class configuration in SOLR_CLASSIFCATION needs to have a pattern and a class configured. Given configuration: ' . serialize($class));
            }

                // @todo deprecate patterns configuration
            $patterns = empty($class['patterns']) ? [] : GeneralUtility::trimExplode(',', $class['patterns']);
            $matchPatterns = empty($class['matchPatterns']) ? [] : GeneralUtility::trimExplode(',', $class['matchPatterns']);
            $matchPatterns = $matchPatterns + $patterns;
            $unMatchPatters = empty($class['unmatchPatterns']) ? [] : GeneralUtility::trimExplode(',', $class['unmatchPatterns']);

            $className = $class['class'];
            $classifications[] = GeneralUtility::makeInstance(
                ClassificationItem::class,
                /** @scrutinizer ignore-type */ $matchPatterns,
                /** @scrutinizer ignore-type */ $unMatchPatters,
                /** @scrutinizer ignore-type */ $className
            );
        }

        return $classifications;
    }
}
