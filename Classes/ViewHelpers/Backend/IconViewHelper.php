<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Backend;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2015 Ingo Renner <ingo@typo3.org>
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

use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Fluid\ViewHelpers\Be\AbstractBackendViewHelper;

/**
 * View helper which returns an icon
 *
 * = Examples =
 *
 * <code title="Default">
 * <solr:backend.icon iconName="FooBar" extension="OtherExtension" />
 * </code>
 * <output>
 * Outputs an icon registered with the sprite manager
 * </output>
 */
class IconViewHelper extends AbstractBackendViewHelper
{

    /**
     * Renders an icon using the sprite manager
     *
     * @param string $iconName Icon name
     * @param string $extension Extension key, defaults to solr
     * @return string the rendered icon
     */
    public function render($iconName, $extension = 'solr')
    {
        $icon = 'extensions-' . $extension . '-' . $iconName;

        return IconUtility::getSpriteIcon($icon);
    }
}
