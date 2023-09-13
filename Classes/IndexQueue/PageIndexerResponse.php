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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use RuntimeException;

/**
 * Index Queue Page Indexer response to provide data for requested actions.
 */
class PageIndexerResponse
{
    /**
     * Unique request ID.
     */
    protected string $requestId;

    /**
     * The actions' results as action => result pairs.
     */
    protected array $results = [];

    /**
     * Turns a JSON encoded result string back into its PHP representation.
     *
     * @param string $jsonEncodedResponse JSON encoded result string
     * @return array|null An array of action => result pairs or NULL if the response could not be decoded
     */
    public static function getResultsFromJson(string $jsonEncodedResponse): ?array
    {
        $responseData = json_decode($jsonEncodedResponse, true);

        if (is_array($responseData['actionResults'] ?? null)) {
            foreach ($responseData['actionResults'] as $action => $serializedActionResult) {
                $responseData['actionResults'][$action] = unserialize($serializedActionResult);
            }
        }

        return $responseData;
    }

    /**
     * Adds an action's result.
     *
     * @param string $action The action name.
     * @param mixed $result The action's result.
     *
     * @throws RuntimeException if $action is null
     */
    public function addActionResult(string $action, mixed $result): void
    {
        $this->results[$action] = $result;
    }

    /**
     * Gets the complete set of results or a specific action's results.
     *
     * @return (string|int|array)[]|string|int|null
     */
    public function getActionResult(?string $action = null): mixed
    {
        if (empty($action)) {
            return $this->results;
        }
        return $this->results[$action];
    }

    /**
     * Compiles the response's content so that it can be sent back to the
     * Index Queue page indexer.
     *
     * @return string The response content
     */
    public function getContent(): string
    {
        return $this->toJson();
    }

    /**
     * Converts the response's data to JSON.
     *
     * @return string JSON representation of the results.
     */
    protected function toJson(): string
    {
        $serializedActionResults = [];

        foreach ($this->results as $action => $result) {
            $serializedActionResults[$action] = serialize($result);
        }

        $responseData = [
            'requestId' => $this->requestId,
            'actionResults' => $serializedActionResults,
        ];

        return json_encode($responseData);
    }

    /**
     * Gets the Id of the request this response belongs to.
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * Sets the Id of the request this response belongs to.
     */
    public function setRequestId(string $requestId): void
    {
        $this->requestId = $requestId;
    }
}
