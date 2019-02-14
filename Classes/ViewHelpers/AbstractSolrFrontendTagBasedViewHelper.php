<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers;

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
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrTagBasedViewHelper;

/**
 * Class AbstractTagBasedViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers
 */
abstract class AbstractSolrFrontendTagBasedViewHelper extends AbstractSolrTagBasedViewHelper
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
        return $this->getControllerContext()->getTypoScriptConfiguration();
    }

    /**
     * @return SearchResultSet
     */
    protected function getSearchResultSet()
    {
        return $this->getControllerContext()->getSearchResultSet();
    }

    /**
     * @return SolrControllerContext
     * @throws \InvalidArgumentException
     */
    protected function getControllerContext()
    {
        $controllerContext = null;
        if (method_exists($this->renderingContext, 'getControllerContext')) {
            $controllerContext = $this->renderingContext->getControllerContext();
        }

        if (!$controllerContext instanceof SolrControllerContext) {
            throw new \InvalidArgumentException('No valid SolrControllerContext found', 1512998673);
        }

        return $controllerContext;
    }
}
