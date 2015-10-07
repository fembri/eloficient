<?php namespace Fembri\Eloficient;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause as BaseJoinClause;

class JoinClause extends BaseJoinClause {

	public function addClause(array $clause) {
		
		$this->clauses[] = $clause;
		
		if (isset($clause["first"])) {
			if ($clause["where"]) {
				$this->bindings[] = $clause["second"];
			} 
		} else {
			if (($clause["type"] == "Basic" && !$clause["value"] instanceof Expression) || in_array($clause["type"], array("Date","Day","Month","Year"))) {
				$this->bindings[] = $clause["value"];
			} elseif ($clause["type"] == "raw") {
				$this->bindings[] = $clause["bindings"];
			} elseif (in_array($clause["type"], array("between","In","NotIn"))) {
				$this->bindings[] = $clause["values"];
			}
		}
	}
}
