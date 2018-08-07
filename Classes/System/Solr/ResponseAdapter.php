<?php
namespace ApacheSolrForTypo3\Solr\System\Solr;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;

/**
 * In EXT:solr 9 we have switched from the SolrPhpClient to the solarium api.
 *
 * In many places of the code the class Apache_Solr_Response and the property Apache_Solr_Response::reponse is used.
 * To be able to refactor this we need to have a replacement for Apache_Solr_Response that behaves like the original class,
 * to keep the old code working. This allows us to drop the old code of SolrPhpClient and refactore the other parts step by step.
 *
 * Class ResponseAdapter
 *
 * Search response
 *
 * @property \stdClass facet_counts
 * @property \stdClass facets
 * @property \stdClass spellcheck
 * @property \stdClass response
 * @property \stdClass responseHeader
 * @property \stdClass highlighting
 * @property \stdClass debug
 * @property \stdClass lucene
 * 
 * Luke response
 *
 * @property \stdClass index
 * @property \stdClass fields
 */
class ResponseAdapter
{
    /**
     * @var string
     */
    protected $responseBody;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var int
     */
    protected $httpStatus = 200;

    /**
     * @var string
     */
    protected $httpStatusMessage = '';

    /**
     * ResponseAdapter constructor.
     *
     * @param string $responseBody
     * @param int $httpStatus
     * @param string $httpStatusMessage
     */
    public function __construct($responseBody, $httpStatus = 500, $httpStatusMessage = '')
    {
        $this->data = json_decode($responseBody);
        $this->responseBody = $responseBody;
        $this->httpStatus = $httpStatus;
        $this->httpStatusMessage = $httpStatusMessage;

        if (isset($this->data->response) && is_array($this->data->response->docs)) {
            $documents = array();

            foreach ($this->data->response->docs as $originalDocument) {
                $fields = get_object_vars($originalDocument);
                $document = new Document($fields);
                $documents[] = $document;
            }

            $this->data->response->docs = $documents;
        }
    }

    /**
     * Magic get to expose the parsed data and to lazily load it
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        if (isset($this->data->$key)) {
            return $this->data->$key;
        }

        return null;
    }

    /**
     * Magic function for isset function on parsed data
     *
     * @param string $key
     * @return boolean
     */
    public function __isset($key)
    {
        return isset($this->data->$key);
    }

    /**
     * @return mixed
     */
    public function getParsedData()
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getRawResponse()
    {
        return $this->responseBody;
    }

    /**
     * @return int
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return string
     */
    public function getHttpStatusMessage(): string
    {
        return $this->httpStatusMessage;
    }
}