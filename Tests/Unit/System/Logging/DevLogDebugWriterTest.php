<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Logging\DevLogDebugWriter;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DevLogDebugWriterTest extends UnitTest
{

    /**
     * @test
     */
    public function testDebugMessageIsWrittenForMessageFromSolr()
    {
        /** @var $logWriter DevLogDebugWriter */
        $logWriter = $this->getMockBuilder(DevLogDebugWriter::class)->setMethods(['getIsAllowedByDevIPMask', 'getIsDevLogDebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects($this->any())->method('getIsAllowedByDevIPMask')->will($this->returnValue(true));
        $logWriter->expects($this->any())->method('getIsDevLogDebugOutputEnabled')->will($this->returnValue(true));

            //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects($this->once())->method('writeDebugMessage');
        $logWriter->log(['extKey' => 'solr', 'message' => 'test']);
    }

    /**
     * @test
     */
    public function testDebugMessageIsNotWrittenForOtherExtensions()
    {
        /** @var $logWriter DevLogDebugWriter */
        $logWriter = $this->getMockBuilder(DevLogDebugWriter::class)->setMethods(['getIsAllowedByDevIPMask', 'getIsDevLogDebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects($this->any())->method('getIsAllowedByDevIPMask')->will($this->returnValue(true));
        $logWriter->expects($this->any())->method('getIsDevLogDebugOutputEnabled')->will($this->returnValue(true));

        //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects($this->never())->method('writeDebugMessage');
        $logWriter->log(['extKey' => 'news', 'message' => 'test']);
    }

    /**
     * @test
     */
    public function testDebugMessageIsNotWrittenWhenDevIpMaskIsNotMatching()
    {
        /** @var $logWriter DevLogDebugWriter */
        $logWriter = $this->getMockBuilder(DevLogDebugWriter::class)->setMethods(['getIsAllowedByDevIPMask', 'getIsDevLogDebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects($this->any())->method('getIsAllowedByDevIPMask')->will($this->returnValue(false));
        $logWriter->expects($this->any())->method('getIsDevLogDebugOutputEnabled')->will($this->returnValue(true));

        //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects($this->never())->method('writeDebugMessage');
        $logWriter->log(['extKey' => 'solr', 'message' => 'test']);
    }

    /**
     * @test
     */
    public function testDebugMessageIsNotWrittenWhenDebugOutputIsDisabled()
    {
        /** @var $logWriter DevLogDebugWriter */
        $logWriter = $this->getMockBuilder(DevLogDebugWriter::class)->setMethods(['getIsAllowedByDevIPMask', 'getIsDevLogDebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects($this->any())->method('getIsAllowedByDevIPMask')->will($this->returnValue(true));
        $logWriter->expects($this->any())->method('getIsDevLogDebugOutputEnabled')->will($this->returnValue(false));

        //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects($this->never())->method('writeDebugMessage');
        $logWriter->log(['extKey' => 'solr', 'message' => 'test']);
    }
}
