<?php

namespace ApacheSolrForTypo3\Solr\System\Data;

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

/**
 * Class DateTime
 */
class DateTime extends \DateTime
{
    /**
     * @param string $time
     * @param \DateTimeZone $timezone
     */
    public function __construct($time = 'now', \DateTimeZone $timezone = null)
    {
        parent::__construct($time, $timezone);
    }

    /**
     * Returns the date formatted as ISO.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->format(\DateTime::ISO8601);
    }
}
