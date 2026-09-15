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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Facet\Options\Group\Prefix;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\OptionCollection;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;

/**
 * Class LabelFilterViewHelper
 */
class LabelFilterViewHelper extends AbstractSolrFrontendViewHelper
{
    protected $escapeOutput = false;

    /**
     * Initializes the arguments
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('options', OptionCollection::class, 'The facets that should be filtered', true);
        $this->registerArgument('prefix', 'string', 'The prefix where options should be filtered on', true);
    }

    /**
     * Renders group label filter
     */
    public function render()
    {
        /** @var OptionCollection $options */
        $options = $this->arguments['options'];
        $requiredPrefix = mb_strtolower($this->arguments['prefix'] ?? '');
        $filtered = $options->getByLowercaseLabelPrefix($requiredPrefix);

        $templateVariableProvider = $this->renderingContext->getVariableProvider();
        $templateVariableProvider->add('filteredOptions', $filtered);
        $content = $this->renderChildren();
        $templateVariableProvider->remove('filteredOptions');

        return $content;
    }
}
