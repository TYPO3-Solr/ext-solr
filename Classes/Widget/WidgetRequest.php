<?php
namespace ApacheSolrForTypo3\Solr\Widget;

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

use TYPO3\CMS\Fluid\Core\Widget\WidgetRequest as CoreWidgetRequest;

/**
 * Class WidgetRequest
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class WidgetRequest extends CoreWidgetRequest
{

    /**
     * Returns the unique URI namespace for this widget in the format pluginNamespace[widgetIdentifier]
     *
     * @return string
     */
    public function getArgumentPrefix()
    {
        // we skip the [@widget] part
        return $this->widgetContext->getParentPluginNamespace();
    }
}
