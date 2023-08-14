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

namespace ApacheSolrForTypo3\Solr\System\Validator;

/**
 * Class Path is used for Solr Path related methods
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class Path
{
    /**
     * Validate that a path is a valid Solr Path
     */
    public function isValidSolrPath(string $path): bool
    {
        $path = trim($path);

        return (!empty($path)) && (preg_match('/^[^*?"<>|:#]*$/', $path));
    }
}
