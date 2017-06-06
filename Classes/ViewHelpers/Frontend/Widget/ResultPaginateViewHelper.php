<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Frontend\Widget;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Frans Saris <frans@beech.it> & Timo Hund <timo.hund@dkd.de>
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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Widget\AbstractWidgetViewHelper;

/**
 * Class ResultPaginateViewHelper
 */
class ResultPaginateViewHelper extends AbstractWidgetViewHelper
{

    /**
     * @var \ApacheSolrForTypo3\Solr\ViewHelpers\Frontend\Widget\Controller\ResultPaginateController
     * @inject
     */
    protected $controller;

    /**
     * @param SearchResultSet $resultSet
     * @param string $as
     * @param array $configuration
     * @return \TYPO3\CMS\Extbase\Mvc\ResponseInterface
     * @throws \TYPO3\CMS\Fluid\Core\Widget\Exception\MissingControllerException
     */
    public function render(SearchResultSet $resultSet, $as = 'documents', array $configuration = ['insertAbove' => true, 'insertBelow' => true, 'maximumNumberOfLinks' => 10])
    {
        return $this->initiateSubRequest();
    }
}
