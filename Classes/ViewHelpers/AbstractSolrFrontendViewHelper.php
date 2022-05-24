<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\ViewHelpers;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use InvalidArgumentException;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class AbstractSolrFrontendViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractSolrFrontendViewHelper extends AbstractSolrViewHelper
{
    /**
     * @var SolrControllerContext|null
     */
    protected ?SolrControllerContext $controllerContext = null;

    /**
     * @return TypoScriptConfiguration|null
     */
    protected function getTypoScriptConfiguration(): ?TypoScriptConfiguration
    {
        return $this->getControllerContext()->getTypoScriptConfiguration();
    }

    /**
     * @return SearchResultSet|null
     */
    protected function getSearchResultSet(): ?SearchResultSet
    {
        return $this->getControllerContext()->getSearchResultSet();
    }

    /**
     * @return SolrControllerContext
     * @throws InvalidArgumentException
     */
    protected function getControllerContext(): SolrControllerContext
    {
        $controllerContext = null;
        if (method_exists($this->renderingContext, 'getControllerContext')) {
            $controllerContext = $this->renderingContext->getControllerContext();
        }

        if (!$controllerContext instanceof SolrControllerContext) {
            throw new InvalidArgumentException('No valid SolrControllerContext found', 1512998673);
        }

        return $controllerContext;
    }

    /**
     * @param RenderingContextInterface $renderingContext
     * @return SearchResultSet|null
     */
    protected static function getUsedSearchResultSetFromRenderingContext(RenderingContextInterface $renderingContext): ?SearchResultSet
    {
        return $renderingContext->getVariableProvider()->get('resultSet');
    }
}
