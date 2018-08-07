<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query;

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

use Solarium\QueryType\Extract\Query as SolariumExtractQuery;

/**
 * Specialized query for content extraction using Solr Cell
 *
 */
class ExtractingQuery extends SolariumExtractQuery
{
    protected $multiPartPostDataBoundary;

    /**
     * Constructor
     *
     * @param string $file Absolute path to the file to extract content and meta data from.
     */
    public function __construct($file)
    {
        parent::__construct();
        $this->setFile($file);
        $this->multiPartPostDataBoundary = '--' . md5(uniqid(time()));
        $this->addParam('extractFormat', 'text');
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
     * Gets the filename portion of the file.
     *
     * @return string The filename.
     */
    public function getFileName()
    {
        return basename($this->getFile());
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

        $fileData = file_get_contents($this->getFile());
        if ($fileData === false) {
            throw new \Apache_Solr_InvalidArgumentException('Could not retrieve content from file ' . $this->getFile());
        }

        $data = "--{$boundary}\r\n";
        // The 'filename' used here becomes the property name in the response.
        $data .= 'Content-Disposition: form-data; name="file"; filename="extracted"';
        $data .= "\r\nContent-Type: application/octet-stream\r\n\r\n";
        $data .= $fileData;
        $data .= "\r\n--{$boundary}--\r\n";

        return $data;
    }
}
