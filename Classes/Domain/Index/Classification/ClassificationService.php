<?php declare(strict_types = 1);
namespace ApacheSolrForTypo3\Solr\Domain\Index\Classification;

    /***************************************************************
     *  Copyright notice
     *
     *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
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

/**
 * Class ClassificationService
 * @package ApacheSolrForTypo3\Solr\Domain\Index\Classification
 */
class ClassificationService {

    /**
     * @param string $stringToMatch
     * @param Classification[] $classifications
     * @return array
     */
    public function getMatchingClassNames(string $stringToMatch, $classifications) : array
    {
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
    protected function applyMatchPatterns(string $stringToMatch, $classification, $matchingClassification): array
    {
        /** @var $classification Classification */
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
     * @param $messages
     * @return array
     */
    protected function applyUnMatchPatterns(string $stringToMatch, $classification, $matchingClassification): array
    {
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