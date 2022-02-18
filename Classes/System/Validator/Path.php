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
     *
     * @param string $path
     * @return bool
     */
    public function isValidSolrPath($path)
    {
        $path = trim($path);

        if ((!empty($path)) && (preg_match('/^[^*?"<>|:#]*$/', $path))) {
            return true;
        }

        return false;
    }
}
