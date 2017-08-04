<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Steffen Ritter <info@rs-websystems.de>
 *
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

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageRendererResolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Update class for the extension manager.
 *
 */
class ext_update
{
    /**
     * Array of flash messages (params) array[][status,title,message]
     *
     * @var array
     */
    protected $messages = [];

    /**
     * @var \ApacheSolrForTypo3\Solrfal\Migrations\Migration[]
     */
    protected $migrators = [];

    /**
     * Constructor initializing all migrations
     */
    public function __construct()
    {
        $this->migrators[] = new \ApacheSolrForTypo3\Solr\Migrations\RemoveSiteFromScheduler();
    }

    /**
     * Called by the extension manager to determine if the update menu entry
     * should by showed.
     *
     * @return bool
     */
    public function access()
    {
        foreach ($this->migrators as $migration) {
            if ($migration->isNeeded()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Main update function called by the extension manager.
     *
     * @return string
     */
    public function main()
    {
        foreach ($this->migrators as $migration) {
            if ($migration->isNeeded()) {
                try {
                    $this->messages[] = $migration->process();
                } catch (\Exception $e) {
                    $this->messages[] = [FlashMessage::ERROR, 'Execution failed', $e->getMessage()];
                }
            }
        }
        return $this->generateOutput();
    }

    /**
     * Generates output by using flash messages
     *
     * @return string
     */
    protected function generateOutput()
    {
        $flashMessages = [];
        foreach ($this->messages as $messageItem) {
            /** @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage */
            $flashMessages[] = GeneralUtility::makeInstance(FlashMessage::class, $messageItem[2], $messageItem[1], $messageItem[0]);
        }

            /** @var $resolver FlashMessageRendererResolver */
        $resolver = GeneralUtility::makeInstance(FlashMessageRendererResolver::class);
        $renderer = $resolver->resolve();
        return $renderer->render($flashMessages);
    }
}
