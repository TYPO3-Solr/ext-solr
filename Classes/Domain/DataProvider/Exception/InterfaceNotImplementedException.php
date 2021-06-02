<?php
declare(strict_types=1);
namespace ApacheSolrForTypo3\Solr\Domain\DataProvider\Exception;

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
 * This exception should thrown in case a given data provider does not implement a required interface.
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class InterfaceNotImplementedException extends \Exception
{
}
