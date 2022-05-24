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
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class PageIndexerResponse
{
    /**
     * Unique request ID.
     *
     * @var string
     */
    protected string $requestId;

    /**
     * The actions' results as action => result pairs.
     *
     * @var array
     */
    protected array $results = [];

    /**
     * Turns a JSON encoded result string back into its PHP representation.
     *
     * @param string $jsonEncodedResponse JSON encoded result string
     * @return array|bool An array of action => result pairs or FALSE if the response could not be decoded
     */
    public static function getResultsFromJson(string $jsonEncodedResponse)
    {
        $responseData = json_decode($jsonEncodedResponse, true);

        if (is_array($responseData['actionResults'] ?? null)) {
            foreach ($responseData['actionResults'] as $action => $serializedActionResult) {
                $responseData['actionResults'][$action] = unserialize($serializedActionResult);
            }
        } elseif (is_null($responseData)) {
            $responseData = false;
        }

        return $responseData;
    }

    /**
     * Adds an action's result.
     *
     * @param string $action The action name.
     * @param mixed $result The action's result.
     * @throws RuntimeException if $action is null
     */
    public function addActionResult(string $action, $result)
    {
        $this->results[$action] = $result;
    }

    /**
     * Gets the complete set of results or a specific action's results.
     *
     * @param string|null $action Optional action name.
     * @return mixed
     */
    public function getActionResult(?string $action = null)
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
     *
     * @return string Request Id.
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * Sets the Id of the request this response belongs to.
     *
     * @param string $requestId Request Id.
     */
    public function setRequestId(string $requestId)
    {
        $this->requestId = $requestId;
    }
}
