<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Ingo Renner <ingo@typo3.org>
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

/**
 * Facet renderer factory, creates facet renderers depending on the configured
 * type of a facet.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_facet_FacetRendererFactory {

	/**
	 * Registration information for facet renderers.
	 * A simple map of facet type => class name
	 *
	 * @var array
	 */
	protected static $facetRenderers = array();

	/**
	 * The default facet render, good for most cases.
	 *
	 * @var string
	 */
	private $defaultFacetRendererClassName = 'tx_solr_facet_SimpleFacetRenderer';

	protected $facetsConfiguration = array();


	public function __construct(array $facetsConfiguration) {
		$this->facetsConfiguration = $facetsConfiguration;
	}

	public static function registerFacetRenderer($rendererClassName, $facetType) {
		self::$facetRenderers[$facetType] = $rendererClassName;
	}

	public function getFacetRendererByFacetName($facetName) {
		$facetRenderer      = NULL;
		$facetConfiguration = $this->facetsConfiguration[$facetName . '.'];

		$facetRendererClassName = $this->defaultFacetRendererClassName;
		if (isset($facetConfiguration['type'])) {
			$facetRendererClassName = $this->getFacetRendererClassNameByFacetType($facetConfiguration['type']);
		}

		$facetRenderer = t3lib_div::makeInstance($facetRendererClassName, $facetName);
		$this->validateObjectIsFacetRenderer($facetRenderer);

		return $facetRenderer;
	}

	protected function getFacetRendererClassNameByFacetType($facetType) {
		if (!array_key_exists($facetType, self::$facetRenderers)) {
			throw new InvalidArgumentException(
				'No renderer configured for facet type "' . $facetType .'"',
				1328041286
			);
		}

		return self::$facetRenderers[$facetType];
	}

	protected function validateObjectIsFacetRenderer($object) {
		if (!($object instanceof tx_solr_FacetRenderer)) {
			throw new UnexpectedValueException(
				get_class($object) . ' is not an implementation of tx_solr_FacetRenderer',
				1328038100
			);
		}
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_facetrendererfactory.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_facetrendererfactory.php']);
}

?>
