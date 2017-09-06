<?php namespace Fembri\Eloficient;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause as BaseJoinClause;
// use Traits\CanModifyArbitraryQueryComponent;

class JoinClause extends BaseJoinClause {
    	
	// use CanModifyArbitraryQueryComponent;

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
