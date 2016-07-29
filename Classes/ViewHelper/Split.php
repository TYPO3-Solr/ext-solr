<?php
namespace ApacheSolrForTypo3\Solr\ViewHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Stefan Sprenger <stefan.sprenger@dkd.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

/**
 * ViewHelper to split a collection and make each part available in the passed argument name.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class Split extends AbstractSubpartViewHelper
{

    /**
     * Constructor
     *
     * @param array $arguments
     */
    public function __construct(array $arguments = array())
    {
    }

    /**
     * Renders a facet.
     *
     * @param array $arguments
     * @return string
     */
    public function execute(array $arguments = array())
    {
        try {
            $iterator  = unserialize($arguments[0]);
        } catch (\Exception $e) {
            $iterator = [];
        }
        if ($iterator === false) {
            $iterator = [];
        }
        $variable  = $arguments[1];

        $data = '';
        foreach ($iterator as $value) {
            $template = clone $this->template;
            $template->addVariable($variable, $value);
            $data .= $template->render();
        }

        return $data;
    }
}
