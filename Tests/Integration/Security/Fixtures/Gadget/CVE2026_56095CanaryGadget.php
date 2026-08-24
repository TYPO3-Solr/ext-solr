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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Security\Fixtures\Gadget;

/**
 * Test-only canary for CVE-2026-56095: its magic methods write a marker file, proving the indexer ran unserialize() on attacker-controlled bytes.
 * PUBLIC properties keep the payload byte-stable through the TEXT cObj stdWrap pipeline
 * (private-property gadgets like GuzzleHttp\Cookie\FileCookieJar carry NUL bytes that stdWrap mangles).
 */
final class CVE2026_56095CanaryGadget
{
    public string $canaryPath = '';
    public string $canaryMarker = '';

    public function __wakeup(): void
    {
        // fires synchronously during unserialize(), before later indexer logic could mask the side effect
        if ($this->canaryPath !== '') {
            @file_put_contents(
                $this->canaryPath . '.wakeup',
                $this->canaryMarker
            );
        }
    }

    public function __destruct()
    {
        if ($this->canaryPath !== '') {
            @file_put_contents(
                $this->canaryPath,
                $this->canaryMarker
            );
        }
    }
}
