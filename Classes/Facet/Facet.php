<?php
namespace ApacheSolrForTypo3\Solr\Facet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A facet
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Facet
{

    const TYPE_FIELD = 'field';
    const TYPE_QUERY = 'query';
    const TYPE_RANGE = 'range';


    /**
     * @var Search
     */
    protected $search;

    /**
     * The facet's name as configured on TypoScript
     *
     * @var string
     */
    protected $name;

    /**
     * Facet type, defaults to field facet.
     *
     * @var string
     */
    protected $type = self::TYPE_FIELD;

    /**
     * The index field the facet is built from.
     *
     * @var string
     */
    protected $field;

    /**
     * Facet configuration
     *
     * @var array
     */
    protected $configuration;


    /**
     * Constructor.
     *
     * @param string $facetName The facet's name
     * @param string $facetType The facet's internal type. field, range, or query
     */
    public function __construct($facetName, $facetType = self::TYPE_FIELD)
    {
        $this->name = $facetName;
        $this->type = $facetType;
        $this->search = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Search');

        $this->initializeConfiguration();
    }

    /**
     * Initializes/loads the facet configuration
     *
     */
    protected function initializeConfiguration()
    {
        $solrConfiguration = Util::getSolrConfiguration();
        $this->configuration = $solrConfiguration->getSearchFacetingFacetByName($this->name);

        $this->field = $this->configuration['field'];
    }

    /**
     * Checks whether an option of the facet has been selected by the user by
     * checking the URL GET parameters.
     *
     * @return boolean TRUE if any option of the facet is applied, FALSE otherwise
     */
    public function isActive()
    {
        $isActive = false;

        $selectedOptions = $this->getSelectedOptions();
        if (!empty($selectedOptions)) {
            $isActive = true;
        }

        return $isActive;
    }

    /**
     * Gets the facet's currently user-selected options
     *
     * @return array An array with user-selected facet options.
     */
    public function getSelectedOptions()
    {
        $selectedOptions = array();

        $resultParameters = GeneralUtility::_GET('tx_solr');
        $filterParameters = array();
        if (isset($resultParameters['filter'])) {
            $filterParameters = (array)array_map('urldecode',
                $resultParameters['filter']);
        }

        foreach ($filterParameters as $filter) {
            list($facetName, $filterValue) = explode(':', $filter);

            if ($facetName == $this->name) {
                $selectedOptions[] = $filterValue;
            }
        }

        return $selectedOptions;
    }

    /**
     * Determines if a facet has any options.
     *
     * @return boolean TRUE if no facet options are given, FALSE if facet options are given
     */
    public function isEmpty()
    {
        $isEmpty = false;

        $options = $this->getOptionsRaw();
        $optionsCount = count($options);

        // facet options include '_empty_', if no options are given
        if ($optionsCount == 0
            || ($optionsCount == 1 && array_key_exists('_empty_', $options))
        ) {
            $isEmpty = true;
        }

        return $isEmpty;
    }

    /**
     * Checks whether requirements are fullfilled
     *
     * @return boolean TRUE if conditions required to render this facet are met, FALSE otherwise
     */
    public function isRenderingAllowed()
    {
        $renderingAllowed = true;

        $requirements = $this->getRequirements();
        foreach ($requirements as $requirement) {
            if (!$this->isRequirementMet($requirement)) {
                $renderingAllowed = false;
                break;
            }
        }

        return $renderingAllowed;
    }

    /**
     * Gets the configured requirements to allow rendering of the facet.
     *
     * @return array Requirements with keys "name", "facet", and "value".
     */
    protected function getRequirements()
    {
        $requirements = array();

        if (!empty($this->configuration['requirements.'])) {
            foreach ($this->configuration['requirements.'] as $name => $requirement) {
                $requirements[] = array(
                    'name' => substr($name, 0, -1),
                    'facet' => $requirement['facet'],
                    'values' => GeneralUtility::trimExplode(',',
                        $requirement['values']),
                );
            }
        }

        return $requirements;
    }

    /**
     * Evaluates a single facet rendering requirement.
     *
     * @param array $requirement A requirement with keys "name", "facet", and "value".
     * @return boolean TRUE if the requirement is met, FALSE otherwise.
     */
    protected function isRequirementMet(array $requirement)
    {
        $requirementMet = false;

        $requiredFacet = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Facet\\Facet',
            $requirement['facet']);
        $selectedOptions = $requiredFacet->getSelectedOptions();
        $csvSelectedOptions = implode(', ', $selectedOptions);

        foreach ($requirement['values'] as $value) {
            $noFacetOptionSelectedRequirementMet = ($value === '__none' && empty($selectedOptions));
            $anyFacetOptionSelectedRequirementMet = ($value === '__any' && !empty($selectedOptions));

            if ($noFacetOptionSelectedRequirementMet
                || $anyFacetOptionSelectedRequirementMet
                || in_array($value, $selectedOptions)
                || fnmatch($value, $csvSelectedOptions)
            ) {
                $requirementMet = true;
                break;
            }
        }

        return $requirementMet;
    }

    /**
     * Gets the facet's options
     *
     * @return array An array with facet options.
     */
    public function getOptionsRaw()
    {
        $facetOptions = array();

        switch ($this->type) {
            case self::TYPE_FIELD:
                $facetOptions = $this->search->getFacetFieldOptions($this->field);
                break;
            case self::TYPE_QUERY:
                $facetOptions = $this->search->getFacetQueryOptions($this->field);
                break;
            case self::TYPE_RANGE:
                $facetOptions = $this->search->getFacetRangeOptions($this->field);
                break;
        }

        return $facetOptions;
    }

    /**
     * Gets the number of options for a facet.
     *
     * @return integer Number of facet options for the current facet.
     */
    public function getOptionsCount()
    {
        $facetOptions = $this->getOptionsRaw();

        return count($facetOptions);
    }

    /**
     * Gets the facet's name
     *
     * @return string The facet's name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the field name the facet is operating on.
     *
     * @return string The name of the field the facet is operating on.
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Gets the facet's configuration.
     *
     * @return array The facet's configuration as an array.
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Gets the facet's internal type. One of field, range, or query.
     *
     * @return string Facet type.
     */
    public function getType()
    {
        return $this->type;
    }
}
