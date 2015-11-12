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
     *  the Free Software Foundation; either version 2 of the License, or
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
 * Exception that should be thrown, when we get an response from solr that
 * was unexpected.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class UnexpectedSolrResponseException extends \Exception
{
    /**
     * @var \Apache_Solr_HttpTransport_Response
     */
    protected $httpResponse;

    /**
     * @param \Apache_Solr_HttpTransport_Response $httpResponse
     */
    public function setHttpResponse($httpResponse)
    {
        $this->httpResponse = $httpResponse;
    }

    /**
     * @return \Apache_Solr_HttpTransport_Response
     */
    public function getHttpResponse()
    {
        return $this->httpResponse;
    }
}