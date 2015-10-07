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
	 * Add a raw where clause to the query.
	 *
	 * @param  string  $sql
	 * @param  array   $bindings
	 * @param  string  $boolean
	 * @return $this
	 */
	public function whereRaw($sql, array $bindings = array(), $boolean = 'and')
	{
		$type = 'raw';

		$this->wheres[] = compact('type', 'sql', 'boolean', 'bindings');

		$this->addBinding($bindings, 'where');

		return $this;
	}
	
	/**
	 * Add a where between statement to the query.
	 *
	 * @param  string  $column
	 * @param  array   $values
	 * @param  string  $boolean
	 * @param  bool  $not
	 * @return $this
	 */
	public function whereBetween($column, array $values, $boolean = 'and', $not = false)
	{
		$type = 'between';

		$this->wheres[] = compact('column', 'type', 'boolean', 'not', 'values');

		$this->addBinding($values, 'where');

		return $this;
	}
	
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
	
	/**
	 * Add a join clause to the query.
	 *
	 * @param  string  $table
	 * @param  string  $one
	 * @param  string  $operator
	 * @param  string  $two
	 * @param  string  $type
	 * @param  bool    $where
	 * @return $this
	 */
	public function join($table, $one, $operator = null, $two = null, $type = 'inner', $where = false)
	{
		// If the first "column" of the join is really a Closure instance the developer
		// is trying to build a join with a complex "on" clause containing more than
		// one condition, so we'll add the join and call a Closure with the query.
		if ($one instanceof Closure)
		{
			$this->joins[] = new JoinClause($type, $table);

			call_user_func($one, end($this->joins));
		}

		// If the column is simply a string, we can assume the join simply has a basic
		// "on" clause with a single condition. So we will just build the join with
		// this simple join clauses attached to it. There is not a join callback.
		else
		{
			$join = new JoinClause($type, $table);

			$this->joins[] = $join->on(
				$one, $operator, $two, 'and', $where
			);
		}

		return $this;
	}
}