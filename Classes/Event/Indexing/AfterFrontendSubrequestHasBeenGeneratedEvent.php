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

namespace ApacheSolrForTypo3\Solr\Event\Indexing;

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use Psr\Http\Message\ServerRequestInterface;

class AfterFrontendSubrequestHasBeenGeneratedEvent
{
    public function __construct(
        protected readonly Item $item,
        protected ServerRequestInterface $request
    ) {
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function setRequest(ServerRequestInterface $request): self
    {
        $this->request = $request;
        return $this;
    }
}
