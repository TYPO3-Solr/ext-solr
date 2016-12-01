<?php
/**
 * Copyright (c) 2007-2011, Servigistics, Inc.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *  - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *  - Neither the name of Servigistics, Inc. nor the names of
 *    its contributors may be used to endorse or promote products derived from
 *    this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @copyright Copyright 2007-2011 Servigistics, Inc. (http://servigistics.com)
 * @license http://solr-php-client.googlecode.com/svn/trunk/COPYING New BSD
 *
 * @version $Id: $
 *
 * @author Timo Schmidt <timo.schmidt@aoemedia.de>, Donovan Jimenez <djimenez@conduit-it.com>
 */
use ApacheSolrForTypo3\Solr\Util;

/**
 * Convenience class that implements the transport implementation. Can be extended by
 * real implementations to do some of the common book keeping.
 */
abstract class Apache_Solr_HttpTransport_Abstract implements Apache_Solr_HttpTransport_Interface
{
    /**
     * Our default timeout value for requests that don't specify a timeout.
     *
     * @var float
     */
    private $_defaultTimeout = false;

    /**
     * Get the current default timeout setting (initially plugin.tx_solr.solr.defaultimeout or default_socket_timeout ini setting)
     * in seconds.
     *
     * @return float
     */
    public function getDefaultTimeout()
    {
        // lazy load the default timeout from the ini settings
        if ($this->_defaultTimeout === false) {
            $solrConfiguration = Util::getSolrConfiguration()->getValueByPathOrDefaultValue('plugin.tx_solr.solr.', array());
            $timeoutValue = (isset($solrConfiguration['defaultimeout']) && (intval($solrConfiguration['defaultimeout']) > 0)) ? intval($solrConfiguration['defaultimeout']) : 0;

            // Check if plugin.tx_solr.solr.defaultimeout is > 0 or else take default_socket_timeout
            if ($timeoutValue > 0) {
                $this->_defaultTimeout = $timeoutValue;
            } else {
                $this->_defaultTimeout = (int) ini_get('default_socket_timeout');

                // double check we didn't get 0 for a timeout
                if ($this->_defaultTimeout <= 0) {
                    $this->_defaultTimeout = 60;
                }
            }
        }

        return $this->_defaultTimeout;
    }

    /**
     * Set the current default timeout for all HTTP requests.
     *
     * @param float $timeout
     */
    public function setDefaultTimeout($timeout)
    {
        $timeout = (float) $timeout;

        if ($timeout >= 0) {
            $this->_defaultTimeout = $timeout;
        }
    }
}
