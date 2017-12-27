<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Widget;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Frans Saris <frans@beech.it> & Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Widget\AbstractWidgetViewHelper;

/**
 * Class GroupItemPaginateViewHelper
 */
class GroupItemPaginateViewHelper extends AbstractWidgetViewHelper
{

    /**
     * @var \ApacheSolrForTypo3\Solr\ViewHelpers\Widget\Controller\GroupItemPaginateController
     * @inject
     */
    protected $controller;


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
