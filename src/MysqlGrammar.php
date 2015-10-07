<?php namespace Fembri\Eloficient;

use Illuminate\Database\Query\Grammars\MySqlGrammar as BaseMySqlGrammar;
use Illuminate\Database\Query\Builder as LaravelBaseBuilder;

class MySqlGrammar extends BaseMySqlGrammar {

	protected function compileJoins(LaravelBaseBuilder $query, $joins)
	{
		$sql = array();

		$query->setBindings(array(), 'join');

		foreach ($joins as $join)
		{
			$table = $this->wrapTable($join->table);

			// First we need to build all of the "on" clauses for the join. There may be many
			// of these clauses so we will need to iterate through each one and build them
			// separately, then we'll join them up into a single string when we're done.
			$clauses = array();

			foreach ($join->clauses as $where)
			{
				if (isset($where['type'])) {
					$method = "where{$where['type']}";

					$clauses[] = $where['boolean'].' '.$this->$method($query, $where);
				} else 
					$clauses[] = $this->compileJoinConstraint($where);
			}

			foreach ($join->bindings as $binding)
			{
				$query->addBinding($binding, 'join');
			}

			// Once we have constructed the clauses, we'll need to take the boolean connector
			// off of the first clause as it obviously will not be required on that clause
			// because it leads the rest of the clauses, thus not requiring any boolean.
			$clauses[0] = $this->removeLeadingBoolean($clauses[0]);

			$clauses = implode(' ', $clauses);

			$type = $join->type;

			// Once we have everything ready to go, we will just concatenate all the parts to
			// build the final join statement SQL for the query and we can then return the
			// final clause back to the callers as a single, stringified join statement.
			$sql[] = "$type join $table on ($clauses)";
		}

		return implode(' ', $sql);
	}
}
