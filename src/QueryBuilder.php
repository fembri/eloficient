<?php

namespace Fembri\Eloficient;

use Illuminate\Database\Query\Builder;

class QueryBuilder extends Builder 
{
	/**
     * Add a join clause to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @param  string  $type
     * @param  bool    $where
     * @return $this
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
    {
        $join = new JoinClause($this, $type, $table);

        // If the first "column" of the join is really a Closure instance the developer
        // is trying to build a join with a complex "on" clause containing more than
        // one condition, so we'll add the join and call a Closure with the query.
        if ($first instanceof Closure) {
            call_user_func($first, $join);

            $this->joins[] = $join;

            $this->addBinding($join->getBindings(), 'join');
        }

        // If the column is simply a string, we can assume the join simply has a basic
        // "on" clause with a single condition. So we will just build the join with
        // this simple join clauses attached to it. There is not a join callback.
        else {
            $method = $where ? 'where' : 'on';

            $this->joins[] = $join->$method($first, $operator, $second);

            $this->addBinding($join->getBindings(), 'join');
        }

        return $this;
    }
    	
	public function addWhere($where) 
	{
		if ($where['type'] == 'Basic') 
			$this->where($where['column'], $where['operator'], $where['value'], $where['boolean']);

		if ($where['type'] == 'Column')
			$this->whereColumn($where['first'], $where['operator'], $where['second'], $where['boolean']);

		if ($where['type'] == 'In' || $where['type'] == 'NotIn')
			$this->whereIn($where['column'], $where['values'], $where['boolean'], $where['type'] == 'NotIn');

		if ($where['type'] == 'InSub' || $where['type'] == 'NotInSub') {

			$callback = function($query) use ($where) {

				$query = $where['query'];
			};

			$this->whereInSub($where['column'], $callback, $where['boolean'], $where['type'] == 'NotInSub');
		}

		if ($where['type'] == 'Null' || $where['type'] == 'NotNull')
			$this->whereNull($where['column'], $where['boolean'], $where['type'] == 'NotNull');

		if (in_array($where['type'], ['Year', 'Month', 'Day', 'Time', 'Date']))
			$this->addDateBasedWhere($where['type'], $where['column'], $where['operator'], $where['value'], $where['boolean']);

		if ($where['type'] == 'Nested')
			$this->whereNull($where['query'], $where['boolean']);

		if ($where['type'] == 'Sub') {

			$callback = function($query) use ($where) {

				$query = $where['query'];
			};

			$this->whereSub($where['column'], $where['operator'], $callback, $where['boolean']);
		}

		return $this;
	}
}