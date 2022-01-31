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

namespace ApacheSolrForTypo3\Solr\System\Solr;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory as CoreRequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RequestFactory extends CoreRequestFactory
{
    protected $clientOptions = [];

    /**
     * RequestFactory constructor.
     * @param array $clientOptions
     */
    public function __construct(array $clientOptions)
    {
        $this->clientOptions = $clientOptions;
    }

    public function request(string $uri, string $method = 'GET', array $options = []): ResponseInterface
    {
        /* @var GuzzleClient $client */
        $client = GeneralUtility::makeInstance(GuzzleClient::class, $this->clientOptions);
        return $client->request($method, $uri, $options);
    }
}
