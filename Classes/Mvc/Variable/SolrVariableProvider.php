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

namespace ApacheSolrForTypo3\Solr\Mvc\Variable;

use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;

/**
 * Extended version of the StandardVariableProvider
 * We added searchResultsSet and TypoScriptConfiguration to variables which would be
 * transferred to each render/section/layout
 */
class SolrVariableProvider extends StandardVariableProvider
{
    /**
     * @inheritDoc
     */
    public function getScopeCopy($variables): VariableProviderInterface
    {
        if (!array_key_exists('settings', $variables) && array_key_exists('settings', $this->variables)) {
            $variables['settings'] = $this->variables['settings'];
        }
        if (!array_key_exists('searchResultSet', $variables) && array_key_exists('searchResultSet', $this->variables)) {
            $variables['searchResultSet'] = $this->variables['searchResultSet'];
        }
        if (!array_key_exists('typoScriptConfiguration', $variables) && array_key_exists('typoScriptConfiguration', $this->variables)) {
            $variables['typoScriptConfiguration'] = $this->variables['typoScriptConfiguration'];
        }

        $className = static::class;

        return new $className($variables);
    }
}
