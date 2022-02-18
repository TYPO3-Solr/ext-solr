<?php

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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Classification;

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
        self::assertSame(['mobilephone'], $matches, 'Unexpected matched classification');

        $matches = $service->getMatchingClassNames('I have a smartphone in my hand and a watch on my arm.', [$mobilePhoneClassification, $watchClassification]);
        self::assertSame(['mobilephone', 'watch'], $matches, 'Unexpected matched classification');

        $matches = $service->getMatchingClassNames('I have nothing on my arm and in my hand.', [$mobilePhoneClassification, $watchClassification]);
        self::assertSame([], $matches, 'Unexpected matched classification');

        $matches = $service->getMatchingClassNames('I like my SMARTPHONE.', [$mobilePhoneClassification, $watchClassification]);
        self::assertSame(['mobilephone'], $matches, 'Unexpected matched classification');
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
        self::assertSame([], $matches, 'Nothing should match');

        $matches = $service->getMatchingClassNames('I have a smartphone in my hand.', [$mobilePhoneClassification]);
        self::assertSame(['mobilephone'], $matches, 'Wildcard not detected');

        $matches = $service->getMatchingClassNames('smarthome is the future.', [$mobilePhoneClassification]);
        self::assertSame([], $matches, 'Unmatch pattern should remove assigned class');

        $matchPatterns = ['\sh.nd\s'];
        $unMatchPatterns = [];
        $mappedClass = 'test';
        $testClassification = new Classification($matchPatterns, $unMatchPatterns, $mappedClass);

        $matches = $service->getMatchingClassNames('ein hund spring', [$testClassification]);
        self::assertSame(['test'], $matches, 'test class should assign');
        $matches = $service->getMatchingClassNames('eine hand klatscht', [$testClassification]);
        self::assertSame(['test'], $matches, 'test class should assign');
        $matches = $service->getMatchingClassNames('die hunde bellen', [$testClassification]);
        self::assertSame([], $matches, 'test class should assign');
    }
}
