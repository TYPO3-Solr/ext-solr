<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
 *
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ResultParserRegistry
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ResultParserRegistry implements SingletonInterface
{
    /**
     * Array of available parser classNames
     *
     * @var array
     */
    protected $parsers = [
        100 => DefaultResultParser::class,
    ];

    /**
     * @var AbstractResultParser[]
     */
    protected $parserInstances;

    /**
     * Get registered parser classNames
     *
     * @return array
     */
    public function getParsers()
    {
        return $this->parsers;
    }

    /**
     * Can be used to register a custom parser.
     *
     * @param string $className classname of the parser that should be used
     * @param int $priority higher priority means more important
     * @throws \InvalidArgumentException
     */
    public function registerParser($className, $priority)
    {
        // check if the class is available for TYPO3 before registering the driver
        if (!class_exists($className)) {
            throw new \InvalidArgumentException('Class ' . $className . ' does not exist.', 1468863997);
        }

        if (!is_subclass_of($className, AbstractResultParser::class)) {
            throw new \InvalidArgumentException('Parser ' . $className . ' needs to implement the AbstractResultParser.', 1468863998);
        }

        if (array_key_exists((int)$priority, $this->parsers)) {
            throw new \InvalidArgumentException('There is already a parser registerd with priority ' . (int)$priority . '.', 1468863999);
        }

        $this->parsers[(int)$priority] = $className;
    }

    /**
     * Method to check if a certain parser is allready registered
     *
     * @param string $className
     * @param int $priority
     * @return boolean
     */
    public function hasParser($className, $priority)
    {
        if (empty($this->parsers[$priority])) {
            return false;
        }

        return $this->parsers[$priority] === $className;
    }

    /**
     * @return AbstractResultParser[]
     */
    public function getParserInstances()
    {
        if ($this->parserInstances === null) {
            ksort($this->parsers);
            $orderedParsers = array_reverse($this->parsers);
            foreach ($orderedParsers as $className) {
                $this->parserInstances[] = $this->createParserInstance($className);
            }
        }
        return $this->parserInstances;
    }

    /**
     * @param SearchResultSet $resultSet
     * @return AbstractResultParser|null
     */
    public function getParser(SearchResultSet $resultSet)
    {
        /** @var AbstractResultParser $parser */
        foreach ($this->getParserInstances() as $parser) {
            if ($parser->canParse($resultSet)) {
                return $parser;
            }
        }
        return null;
    }

    /**
     * Create an instance of a certain parser class
     *
     * @return AbstractResultParser
     */
    protected function createParserInstance($className)
    {
        return GeneralUtility::makeInstance($className);
    }
}
