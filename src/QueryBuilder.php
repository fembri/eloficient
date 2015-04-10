<?php namespace Fembri\Eloficient;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\Query\Expression;

class QueryBuilder extends \Illuminate\Database\Query\Builder 
{
	/**
	 * Add a "having" clause to the query.
	 *
	 * @param  string  $column
	 * @param  string  $operator
	 * @param  string  $value
	 * @param  string  $boolean
	 * @return $this
	 */
	public function having($column, $operator = null, $value = null, $boolean = 'and')
	{
		$type = 'basic';

		$this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean');
		
		if ( ! $value instanceof Expression)
		{
			$this->addBinding($value, 'having');
		}

		return $this;
	}
}