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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events;

/**
 * Event fired if a page is moved
 */
class PageMovedEvent extends AbstractDataUpdateEvent
{
    /**
     * Constructor
     *
     * @param int $uid
     */
    public function __construct(int $uid)
    {
        parent::__construct($uid, 'pages');
    }
}
