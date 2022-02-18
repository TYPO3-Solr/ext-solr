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

namespace ApacheSolrForTypo3\Solr\FieldProcessor;

/**
 * Field Processor interface
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface FieldProcessor
{

    /**
     * process method
     *
     * @param array $values Array of values, an array because of multivalued fields
     * @return array Modified array of values
     */
    public function process(array $values);
}
