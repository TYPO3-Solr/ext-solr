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
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;

/**
 * Class AbstractSolrFrontendTagBasedViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractSolrFrontendTagBasedViewHelper extends AbstractSolrTagBasedViewHelper
{
    /**
     * @var SolrControllerContext|null
     */
    protected ?SolrControllerContext $controllerContext = null;

    /**
     * @return TypoScriptConfiguration
     */
    protected function getTypoScriptConfiguration(): TypoScriptConfiguration
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
     * @return ControllerContext|SolrControllerContext
     * @throws InvalidArgumentException
     */
    protected function getControllerContext(): ControllerContext
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
}
