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
 * Facet Renderer Interface
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface tx_solr_FacetRenderer {

	/**
	 * Sets the template to use to render the facet.
	 *
	 * @param tx_solr_Template $template Template
	 */
	public function setTemplate(tx_solr_Template $template);

	/**
	 * Sets the target page ID for all links generated
	 *
	 * @param integer $linkTargetPageId Target page ID for links
	 */
	public function setLinkTargetPageId($linkTargetPageId);

	/**
	 * Renders the complete facet.
	 *
	 * @return string The rendered facet
	 */
	public function renderFacet();

	/**
	 * Provides the internal type of facets the renderer handles.
	 * The type is one of field, range, or query.
	 *
	 * @return string Facet internal type
	 */
	public static function getFacetInternalType();

	/**
	 * Gets the facet object markers for use in templates.
	 *
	 * @return array An array with facet object markers.
	 */
	public function getFacetProperties();

	/**
	 * Gets the facet's options
	 *
	 * @return array An array with facet options.
	 */
	public function getFacetOptions();

	/**
	 * Gets the number of options for a facet.
	 *
	 * @return integer Number of facet options for the current facet.
	 */
	public function getFacetOptionsCount();

}

?>