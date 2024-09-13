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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Search;

use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\AbstractUriViewHelper;

/**
 * Class StartNewSearchViewHelper
 */
class StartNewSearchViewHelper extends AbstractUriViewHelper
{
    /**
     * @inheritdoc
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('queryString', 'string', 'The query string', false, '');
    }

    /**
     * Renders for starting the new search
     */
    public function render()
    {
        $queryString = $this->arguments['queryString'];
        $previousRequest = static::getUsedSearchRequestFromRenderingContext($this->renderingContext);
        return self::getSearchUriBuilder($this->renderingContext)->getNewSearchUri($previousRequest, $queryString);
    }
}
