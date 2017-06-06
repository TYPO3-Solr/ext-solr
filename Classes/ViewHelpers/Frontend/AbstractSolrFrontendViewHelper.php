<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Frontend;


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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class AbstractViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers
 */
abstract class AbstractSolrFrontendViewHelper extends AbstractSolrViewHelper
{
    /**
     * @var SolrControllerContext
     */
    protected $controllerContext;

    /**
     * @return TypoScriptConfiguration
     */
    protected function getTypoScriptConfiguration()
    {
        return $this->controllerContext->getTypoScriptConfiguration();
    }

    /**
     * @return SearchResultSet
     */
    protected function getSearchResultSet()
    {
        return $this->controllerContext->getSearchResultSet();
    }

    /**
     * @param RenderingContextInterface $renderingContext
     * @return SearchResultSet
     */
    protected static function getUsedSearchResultSetFromRenderingContext(RenderingContextInterface $renderingContext)
    {
        $resultSet = $renderingContext->getVariableProvider()->get('resultSet');
        return $resultSet;
    }
}
