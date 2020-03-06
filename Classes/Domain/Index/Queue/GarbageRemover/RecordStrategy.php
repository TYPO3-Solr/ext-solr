<?php

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 - Timo Hund <timo.hund@dkd.de>
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
/**
 * Class RecordStrategy
 */
class RecordStrategy extends AbstractStrategy  {

    /**
     * Removes the garbage of a record.
     *
     * @param string $table
     * @param int $uid
     * @return mixed
     */
    protected function removeGarbageOfByStrategy($table, $uid)
    {
        $this->deleteInSolrAndRemoveFromIndexQueue($table, $uid);
    }
}
