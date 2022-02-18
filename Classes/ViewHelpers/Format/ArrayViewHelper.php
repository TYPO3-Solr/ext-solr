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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Format;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Class ArrayViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ArrayViewHelper extends AbstractViewHelper
{

    /**
     * Make sure values is a array else convert
     *
     * @return array
     */
    public function render()
    {
        $value = $this->arguments['value'];
        return (array)$value;
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('value', 'array|string', '', true);
    }
}
