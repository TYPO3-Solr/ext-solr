<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

/**
 * Specialized query for content extraction using Solr Cell
 *
 */
class ExtractingQuery extends Query
{
    protected $file;
    protected $multiPartPostDataBoundary;

    /**
     * Constructor
     *
     * @param string $file Absolute path to the file to extract content and meta data from.
     */
    public function __construct($file)
    {
        parent::__construct('');

        $this->file = $file;
        $this->multiPartPostDataBoundary = '--' . md5(uniqid(time()));
    }

    /**
     * Returns the boundary used for this multi-part form-data POST body data.
     *
     * @return string multi-part form-data POST boundary
     */
    public function getMultiPartPostDataBoundary()
    {
        return $this->multiPartPostDataBoundary;
    }

    /**
     * Gets the absolute path to the file to extract content and meta data from.
     *
     * @return string Absolute path to the file to extract content and meta data from.
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Sets the absolute path to the file to extract content and meta data from.
     *
     * @param string $file Absolute path to the file to extract content and meta data from.
     */
    public function setFile($file)
    {
        if (is_file($file)) {
            $this->file = $file;
        }
    }

    /**
     * Gets the filename portion of the file.
     *
     * @return string The filename.
     */
    public function getFileName()
    {
        return basename($this->file);
    }

    /**
     * Constructs a multi-part form-data POST body from the file's content.
     *
     * @param string $boundary Optional boundary to use
     * @return string The file to extract as raw POST data.
     * @throws \Apache_Solr_InvalidArgumentException
     */
    public function getRawPostFileData($boundary = '')
    {
        if (empty($boundary)) {
            $boundary = $this->multiPartPostDataBoundary;
        }

        $fileData = file_get_contents($this->file);
        if ($fileData === false) {
            throw new \Apache_Solr_InvalidArgumentException(
                'Could not retrieve content from file ' . $this->file
            );
        }

        $data = "--{$boundary}\r\n";
        // The 'filename' used here becomes the property name in the response.
        $data .= 'Content-Disposition: form-data; name="file"; filename="extracted"';
        $data .= "\r\nContent-Type: application/octet-stream\r\n\r\n";
        $data .= $fileData;
        $data .= "\r\n--{$boundary}--\r\n";

        return $data;
    }

    /**
     * En / Disables extraction only
     *
     * @param bool $extractOnly If TRUE, only extracts content from the given file without indexing
     */
    public function setExtractOnly($extractOnly = true)
    {
        if ($extractOnly) {
            $this->queryParameters['extractOnly'] = 'true';
        } else {
            unset($this->queryParameters['extractOnly']);
        }
    }

    /**
     * Builds an array of query parameters to use for the search query.
     *
     * @return array An array ready to use with query parameters
     */
    public function getQueryParameters()
    {
        $filename = basename($this->file);

        // TODO create an Apache Solr patch to support Apache Tika's -m (and -l) options
        $suggestParameters = [
            'resource.name' => $filename,
            'extractFormat' => 'text',
            // Matches the -t command for the tika CLI app.
        ];

        return array_merge($suggestParameters, $this->queryParameters);
    }
}
