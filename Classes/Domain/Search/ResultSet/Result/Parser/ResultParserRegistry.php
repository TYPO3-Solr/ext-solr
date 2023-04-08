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
use InvalidArgumentException;
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
    protected array $parsers = [
        100 => DefaultResultParser::class,
    ];

    /**
     * @var AbstractResultParser[]|null
     */
    protected ?array $parserInstances = null;

    /**
     * Get registered parser classNames
     *
     * @return array
     */
    public function getParsers(): array
    {
        return $this->parsers;
    }

    /**
     * Can be used to register a custom parser.
     *
     * @param string $className classname of the parser that should be used
     * @param int $priority higher priority means more important
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
     *
     * @param string $className
     * @param int $priority
     * @return bool
     */
    public function hasParser(string $className, int $priority): bool
    {
        if (empty($this->parsers[$priority])) {
            return false;
        }

        return $this->parsers[$priority] === $className;
    }

    /**
     * @return array|null|AbstractResultParser[]
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
     * @param SearchResultSet $resultSet
     * @return AbstractResultParser|null
     */
    public function getParser(SearchResultSet $resultSet): ?AbstractResultParser
    {
        /* @var AbstractResultParser $parser */
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
     * @param string $className
     * @return AbstractResultParser
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    protected function createParserInstance(string $className): AbstractResultParser
    {
        return GeneralUtility::makeInstance($className);
    }
}
