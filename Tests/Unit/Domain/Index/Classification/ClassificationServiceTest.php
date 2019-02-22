<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Classification;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Index\Classification\Classification;
use ApacheSolrForTypo3\Solr\Domain\Index\Classification\ClassificationService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ClassificationServiceTest extends UnitTest
{

    /**
     * @test
     */
    public function canGetMatchingClassifications()
    {
        $matchPatterns = ['smartphones', 'handy', 'smartphone', 'mobile', 'mobilephone'];
        $unMatchPatterns = [];
        $mappedClass = 'mobilephone';
        $mobilePhoneClassification = new Classification($matchPatterns, $unMatchPatterns, $mappedClass);

        $matchPatterns = ['clock', 'watch', 'watches'];
        $mappedClass = 'watch';
        $watchClassification = new Classification($matchPatterns, $unMatchPatterns, $mappedClass);

        $service = new ClassificationService();
        $matches = $service->getMatchingClassNames('I have a smartphone in my hand.', [$mobilePhoneClassification, $watchClassification]);
        $this->assertSame(['mobilephone'], $matches, 'Unexpected matched classification');

        $matches = $service->getMatchingClassNames('I have a smartphone in my hand and a watch on my arm.', [$mobilePhoneClassification, $watchClassification]);
        $this->assertSame(['mobilephone', 'watch'], $matches, 'Unexpected matched classification');

        $matches = $service->getMatchingClassNames('I have nothing on my arm and in my hand.', [$mobilePhoneClassification, $watchClassification]);
        $this->assertSame([], $matches, 'Unexpected matched classification');

        $matches = $service->getMatchingClassNames('I like my SMARTPHONE.', [$mobilePhoneClassification, $watchClassification]);
        $this->assertSame(['mobilephone'], $matches, 'Unexpected matched classification');
    }

    /**
     * @test
     */
    public function canMatchWildCards()
    {
        $matchPatterns = ['\ssmart[a-z]*\s'];
        $unMatchPatterns = ['home'];
        $mappedClass = 'mobilephone';
        $mobilePhoneClassification = new Classification($matchPatterns, $unMatchPatterns, $mappedClass);

        $service = new ClassificationService();
        $matches = $service->getMatchingClassNames('', [$mobilePhoneClassification]);
        $this->assertSame([], $matches, 'Nothing should match');

        $matches = $service->getMatchingClassNames('I have a smartphone in my hand.', [$mobilePhoneClassification]);
        $this->assertSame(['mobilephone'], $matches, 'Wildcard not detected');

        $matches = $service->getMatchingClassNames('smarthome is the future.', [$mobilePhoneClassification]);
        $this->assertSame([], $matches, 'Unmatch pattern should remove assigned class');

        $matchPatterns = ['\sh.nd\s'];
        $unMatchPatterns = [];
        $mappedClass = 'test';
        $testClassification = new Classification($matchPatterns, $unMatchPatterns, $mappedClass);

        $matches = $service->getMatchingClassNames('ein hund spring', [$testClassification]);
        $this->assertSame(['test'], $matches, 'test class should assign');
        $matches = $service->getMatchingClassNames('eine hand klatscht', [$testClassification]);
        $this->assertSame(['test'], $matches, 'test class should assign');
        $matches = $service->getMatchingClassNames('die hunde bellen', [$testClassification]);
        $this->assertSame([], $matches, 'test class should assign');
    }
}