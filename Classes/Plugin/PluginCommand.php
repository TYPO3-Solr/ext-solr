<?php
namespace ApacheSolrForTypo3\Solr\Plugin;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Plugin command interface
 *
 * @deprecated Not supported with fluid templating, will be removed in 8.0
 * @author Ingo Renner <ingo@typo3.org>
 */
interface PluginCommand
{
    const REQUIREMENTS_NUM_BITS = 4;

    const REQUIREMENT_NONE = 1; // 0001
    const REQUIREMENT_HAS_SEARCHED = 2; // 0010
    const REQUIREMENT_NO_RESULTS = 4; // 0100
    const REQUIREMENT_HAS_RESULTS = 8; // 1000

    /**
     * Constructor.
     *
     * FIXME interface must not define a constructor, change this to a setter
     *
     * @param CommandPluginBase $parent Parent plugin object.
     */
    public function __construct(CommandPluginBase $parent);

    /**
     * execute method
     *
     */
    public function execute();
}
