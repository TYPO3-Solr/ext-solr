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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
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
     * @var string[]
     */
    protected array $parsers = [
        100 => DefaultResultParser::class,
    ];

    protected ?array $parserInstances = null;

    /**
     * Get registered parser classNames
     */
    public function getParsers(): array
    {
        return $this->parsers;
    }

    /**
     * Can be used to register a custom parser class by priority(higher priority means more important).
     *
     * @throws InvalidArgumentException
     */
    public function registerParser(string $className, int $priority): void
    {
        // check if the class is available for TYPO3 before registering the driver
        if (!class_exists($className)) {
            throw new InvalidArgumentException('Class ' . $className . ' does not exist.', 1468863997);
        }

        if (!is_subclass_of($className, AbstractResultParser::class)) {
            throw new InvalidArgumentException('Parser ' . $className . ' needs to implement the AbstractResultParser.', 1468863998);
        }

        if (array_key_exists($priority, $this->parsers)) {
            throw new InvalidArgumentException('There is already a parser registered with priority ' . $priority . '.', 1468863999);
        }

        $this->parsers[$priority] = $className;
    }

    /**
     * Method to check if a certain parser is already registered
     */
    public function hasParser(string $className, int $priority): bool
    {
        if (empty($this->parsers[$priority])) {
            return false;
        }

        return $this->parsers[$priority] === $className;
    }

    /**
     * Returns an array of available parser instances
     *
     * @return AbstractResultParser[]|null
     */
    public function getParserInstances(): ?array
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
     * Returns parser instances, which can parse a given result set.
     */
    public function getParser(SearchResultSet $resultSet): ?AbstractResultParser
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
     */
    protected function createParserInstance(string $className): AbstractResultParser
    {
        return GeneralUtility::makeInstance($className);
    }
}
