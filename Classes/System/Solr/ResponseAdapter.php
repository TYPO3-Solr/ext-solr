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

use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use Countable;
use stdClass;

/**
 * In EXT:solr 9 we have switched from the SolrPhpClient to the solarium api.
 *
 * In many places of the code the class Apache_Solr_Response and the property Apache_Solr_Response::response is used.
 * To be able to refactor this we need to have a replacement for Apache_Solr_Response that behaves like the original class,
 * to keep the old code working. This allows us to drop the old code of SolrPhpClient and refactor the other parts step by step.
 *
 * Class ResponseAdapter
 *
 * Search response
 *
 * @property stdClass|null facet_counts
 * @property stdClass|null facets
 * @property stdClass|null spellcheck
 * @property stdClass|null response
 * @property stdClass|null responseHeader
 * @property stdClass|null highlighting
 * @property stdClass|null debug
 * @property stdClass|null lucene
 * @property string file
 * @property array file_metadata
 *
 * Luke response
 *
 * @property stdClass index
 * @property stdClass fields
 * @property stdClass $plugins
 */
class ResponseAdapter implements Countable
{
    protected ?string $responseBody = null;

    protected ?stdClass $data = null;

    protected int $httpStatus = 200;

    protected string $httpStatusMessage = '';

    public function __construct(?string $responseBody, int $httpStatus = 500, string $httpStatusMessage = '')
    {
        $this->data = json_decode($responseBody ?? '');
        $this->responseBody = $responseBody;
        $this->httpStatus = $httpStatus;
        $this->httpStatusMessage = $httpStatusMessage;

        // @extensionScannerIgnoreLine
        if (isset($this->data->response) && is_array($this->data->response->docs ?? null)) {
            $documents = [];

            // @extensionScannerIgnoreLine
            foreach ($this->data->response->docs as $originalDocument) {
                $fields = get_object_vars($originalDocument);
                $document = new Document($fields);
                $documents[] = $document;
            }

            // @extensionScannerIgnoreLine
            $this->data->response->docs = $documents;
        }
    }

    /**
     * Magic get to expose the parsed data and to lazily load it
     */
    public function __get(string $key)
    {
        if (isset($this->data->$key)) {
            return $this->data->$key;
        }

        return null;
    }

    /**
     * Magic function for isset function on parsed data
     */
    public function __isset(string $key)
    {
        return isset($this->data->$key);
    }

    /**
     * Returns the data as an accessible stdClass if available.
     */
    public function getParsedData(): ?stdClass
    {
        return $this->data;
    }

    /**
     * Returns raw response body if available.
     */
    public function getRawResponse(): ?string
    {
        return $this->responseBody;
    }

    /**
     * Returns HTTP status code.
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Returns HTTP status message.
     */
    public function getHttpStatusMessage(): string
    {
        return $this->httpStatusMessage;
    }

    /**
     * Counts the elements of data.
     */
    public function count(): int
    {
        if ($this->data !== null) {
            return count(get_object_vars($this->data));
        }
        return 0;
    }
}
