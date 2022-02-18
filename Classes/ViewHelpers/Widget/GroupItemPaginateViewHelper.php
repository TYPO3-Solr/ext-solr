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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Widget;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\ViewHelpers\Widget\Controller\GroupItemPaginateController;
use ApacheSolrForTypo3\Solr\Widget\AbstractWidgetViewHelper;

/**
 * Class GroupItemPaginateViewHelper
 */
class GroupItemPaginateViewHelper extends AbstractWidgetViewHelper
{

    /**
     * @var GroupItemPaginateController
     */
    protected $controller;

    /**
     * @param GroupItemPaginateController $groupItemPaginateController
     */
    public function injectGroupItemPaginateController(GroupItemPaginateController $groupItemPaginateController)
    {
        $this->controller = $groupItemPaginateController;
    }

    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('resultSet', SearchResultSet::class, 'resultSet', true);
        $this->registerArgument('groupItem', GroupItem::class, 'groupItem', true);
        $this->registerArgument('as', 'string', 'as', false, 'documents');
        $this->registerArgument('configuration', 'array', 'configuration', false, ['insertAbove' => true, 'insertBelow' => true, 'maximumNumberOfLinks' => 10, 'templatePath' => '']);
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
