<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use Doctrine\Common\Proxy\Exception\InvalidArgumentException;

/**
 * The Operator ParameterProvider is responsible to build the solr query parameters
 * that are needed for the operator q.op.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder
 */
class Operator extends AbstractDeactivatableParameterBuilder implements ParameterBuilder
{
    const OPERATOR_AND = 'AND';
    const OPERATOR_OR = 'OR';

    /**
     * @var string
     */
    protected $operator = 'AND';

    /**
     * Faceting constructor.
     *
     * @param bool $isEnabled
     * @param string $operator
     */
    public function __construct($isEnabled, $operator = Operator::OPERATOR_AND)
    {
        $this->isEnabled = $isEnabled;
        $this->setOperator($operator);
    }

    /**
     * @param string $operator
     */
    public function setOperator($operator)
    {
        if (!in_array($operator, [self::OPERATOR_AND, self::OPERATOR_OR])) {
            throw new InvalidArgumentException("Invalid operator");
        }

        $this->operator = $operator;
    }

    /**
     * @return Operator
     */
    public static function getEmpty(): Operator
    {
        return new Operator(false);
    }

    /**
     * @return Operator
     */
    public static function getAnd(): Operator
    {
        return new Operator(true, static::OPERATOR_AND);
    }

    /**
     * @return Operator
     */
    public static function getOr(): Operator
    {
        return new Operator(true, static::OPERATOR_OR);
    }

    /**
     * @param Query $query
     * @return Query
     */
    public function build(Query $query): Query
    {
        if (!$this->isEnabled) {
            $query->getQueryParametersContainer()->remove('q.op');
            return $query;
        }

        $query->getQueryParametersContainer()->set('q.op', $this->operator);
        return $query;
    }

    /**
     * @param string $operator
     * @return Operator
     */
    public static function fromString($operator)
    {
        return new Operator(true, $operator);
    }
}