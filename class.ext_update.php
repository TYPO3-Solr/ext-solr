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

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageRendererResolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ApacheSolrForTypo3\Solr\Migrations\RemoveSiteFromScheduler;

/**
 * Update class for the extension manager.
 *
 * @author Steffen Ritter <info@rs-websystems.de>
 *
 * @noinspection PhpMultipleClassDeclarationsInspection
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
     * @var \ApacheSolrForTypo3\Solr\Migrations\Migration[]
     */
    protected $migrators = [];

    /**
     * Constructor initializing all migrations
     */
    public function __construct() {
        $this->migrators[] = new RemoveSiteFromScheduler();
    }

    /**
     * Called by the extension manager to determine if the update menu entry
     * should by showed.
     *
     * @return bool
     */
    public function access() {
        foreach ($this->migrators as $migration) {
            if ($migration->isNeeded()) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Main update function called by the extension manager.
     *
     * @return string
     */
    public function main() {
        foreach ($this->migrators as $migration) {
            if ($migration->isNeeded()) {
                try {
                    $this->messages[] = $migration->process();
                } catch (\Throwable $e) {
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
    protected function generateOutput() {
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
