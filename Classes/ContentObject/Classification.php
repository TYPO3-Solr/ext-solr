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

use ApacheSolrForTypo3\Solr\Domain\Index\Classification\Classification as ClassificationItem;
use ApacheSolrForTypo3\Solr\Domain\Index\Classification\ClassificationService;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
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
    public const CONTENT_OBJECT_NAME = 'SOLR_CLASSIFICATION';

    /**
     * Executes the SOLR_CLASSIFICATION content object.
     *
     * Returns mapped classes when the field matches on of the configured patterns ...
     *
     * @noinspection PhpMissingReturnTypeInspection, because foreign source inheritance See {@link AbstractContentObject::render()}
     */
    public function render($conf = [])
    {
        if (!is_array($conf['classes.'])) {
            throw new InvalidArgumentException(
                'No class configuration configured for SOLR_CLASSIFICATION object. Given configuration: ' . serialize($conf),
                8365879715,
            );
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
        /** @var ClassificationService $classificationService */
        $classificationService = GeneralUtility::makeInstance(ClassificationService::class);

        return serialize($classificationService->getMatchingClassNames((string)$data, $classifications));
    }

    /**
     * Builds an array of Classification objects from the passed classification configuration.
     *
     * @return ClassificationItem[]
     */
    protected function buildClassificationsFromConfiguration(array $configuredMappedClasses): array
    {
        $classifications = [];
        foreach ($configuredMappedClasses as $class) {
            if ((empty($class['patterns']) && empty($class['matchPatterns'])) || empty($class['class'])) {
                throw new InvalidArgumentException(
                    'A class configuration in SOLR_CLASSIFCATION needs to have a pattern and a class configured. Given configuration: ' . serialize($class),
                    8715165614,
                );
            }

            // @todo deprecate patterns configuration
            $patterns = empty($class['patterns']) ? [] : GeneralUtility::trimExplode(',', $class['patterns']);
            $matchPatterns = empty($class['matchPatterns']) ? [] : GeneralUtility::trimExplode(',', $class['matchPatterns']);
            $matchPatterns += $patterns;
            $unMatchPatters = empty($class['unmatchPatterns']) ? [] : GeneralUtility::trimExplode(',', $class['unmatchPatterns']);

            $className = $class['class'];
            $classifications[] = GeneralUtility::makeInstance(
                ClassificationItem::class,
                $matchPatterns,
                $unMatchPatters,
                $className
            );
        }

        return $classifications;
    }
}
