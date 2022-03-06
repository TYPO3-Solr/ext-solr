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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Classification;

/**
 * Class ClassificationService
 */
class ClassificationService
{

    /**
     * @param string $stringToMatch
     * @param Classification[] $classifications
     * @return array
     */
    public function getMatchingClassNames(
        string $stringToMatch,
        array $classifications
    ): array {
        $matchingClassification = [];
        foreach ($classifications as $classification) {
            $matchingClassification = $this->applyMatchPatterns($stringToMatch, $classification, $matchingClassification);
            $matchingClassification = $this->applyUnMatchPatterns($stringToMatch, $classification, $matchingClassification);
        }

        return array_values($matchingClassification);
    }

    /**
     * @param string $stringToMatch
     * @param Classification $classification
     * @param $matchingClassification
     * @return array
     */
    protected function applyMatchPatterns(
        string $stringToMatch,
        Classification $classification,
        $matchingClassification
    ): array {
        foreach ($classification->getMatchPatterns() as $matchPattern) {
            if (preg_match_all('~' . $matchPattern . '~ims', $stringToMatch) > 0) {
                $matchingClassification[] = $classification->getMappedClass();
                // if we found one match, we do not need to check the other patterns
                break;
            }
        }
        return array_unique($matchingClassification);
    }

    /**
     * @param string $stringToMatch
     * @param Classification $classification
     * @param $matchingClassification
     * @return array
     */
    protected function applyUnMatchPatterns(
        string $stringToMatch,
        Classification $classification,
        $matchingClassification
    ): array {
        foreach ($classification->getUnMatchPatterns() as $unMatchPattern) {
            if (preg_match_all('~' . $unMatchPattern . '~ims', $stringToMatch) > 0) {
                // if we found one match, we do not need to check the other patterns
                $position = array_search($classification->getMappedClass(), $matchingClassification);
                if ($position !== false) {
                    unset($matchingClassification[$position]);
                }
            }
        }

        return $matchingClassification;
    }
}
