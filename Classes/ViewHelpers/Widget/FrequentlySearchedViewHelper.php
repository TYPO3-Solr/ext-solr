<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Widget;

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

use ApacheSolrForTypo3\Solr\ViewHelpers\Widget\Controller\FrequentlySearchedController;
use ApacheSolrForTypo3\Solr\Widget\AbstractWidgetViewHelper;

/**
 * Class FrequentlySearchedViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class FrequentlySearchedViewHelper extends AbstractWidgetViewHelper
{

    /**
     * @var FrequentlySearchedController
     */
    protected $controller;

    /**
     * @param FrequentlySearchedController $controller
     */
    public function injectFrequentlySearchedController(FrequentlySearchedController $controller)
    {
        $this->controller = $controller;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Mvc\ResponseInterface
     * @throws \TYPO3\CMS\Fluid\Core\Widget\Exception\MissingControllerException
     */
    public function render()
    {
        return $this->initiateSubRequest();
    }
}
