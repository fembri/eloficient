<?php namespace Fembri\Eloficient;

use Closure;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends EloquentBuilder {
	
	const RELATION_PREFIX = "rel";
	const FIELD_SEPARATOR = "#F#";
	const GROUP_SEPARATOR = "|G|";
	const OBSERVER_PREFIX = "obs";
	
	protected $supportedJoinCondition = array(
		"Basic"
	);
	protected $disableEloficient = false;
	protected $relations;
	protected $relationLibrary;
	protected $queryComponents = array(
		'wheres',
		'havings',
		'orders',
	);
	protected $observer = array();
	protected $search;
	protected $prepareForPagination;
	
	public function unEloficient()
	{
		$this->disableEloficient = true;
		return $this;
	}
	
	public function search($value)
	{
		$this->search = $value;
		return $this;
	}
	
	public function paginate($perPage = null, $columns = array("*"))
	{
		if ($this->disableEloficient) return parent::paginate($perPage, $columns = array("*"));
		
		$perPage = $perPage ?: ($_GET["perpage"] ?: $this->model->getPerPage());

		$paginator = $this->query->getConnection()->getPaginator();
		
		$this->prepareForPagination = true;
		
		$this->query->forPage($paginator->getCurrentPage(), $perPage);
		
		return $paginator->make($this->get($columns)->all(), $this->getPaginationTotalRows(), $perPage);
	}
	
	public function getPaginationTotalRows()
	{
		$total = $this->model->getConnection()->selectOne("SELECT FOUND_ROWS() AS total");
		return $total->total ?: 0;
	}
	
	/**
	 * Create a new Eloficient builder instance.
	 *
	 * @param  \Illuminate\Database\Eloquent\Builder  $query
	 * @return void
	 */

	public function get($columns = array("*"))
	{
		if ($this->disableEloficient) return parent::get($columns);
		
		if ($this->eagerLoad) {
			
			$this->prepareQuery();
			
			$this->buildRelationshipTree();
			
			$this->applyRelationshipQuery($this->relations);
			
			$this->reformatQueryComponents();
			
			$this->query->columns = array_merge(
				$this->getColumns($this->relations),
				$this->getObserverColumns()
			);
			
			$this->applySearch();
			
			$models = $this->buildModelsFromRelationshipTree(
				$this->relations, 
				$results = $this->query->get()
			);
			
		} else 
			$models = $this->getModels($columns);
		
		
		
		return $this->model->newCollection($models);
	}
	
	public function prepareQuery()
	{
		$this->query->columns = array();
		$this->query->groups = array();
		$this->query->joins = array();
		/*
		if ($this->prepareForPagination)
			$this->query->columns[] = $this->query->raw("SQL_CALC_FOUND_ROWS");
		*/
	}
	
	public function buildRelationshipTree()
	{
		$this->relations = array();
		$this->relationLibrary = array();
		$prefix = static::RELATION_PREFIX;
		$childs = array();
		
		foreach($this->eagerLoad as $fullName => $constraint) {
			$id = count($this->relationLibrary) + 1;
				
			if (strpos($fullName, ".") === false) {
				$name = $fullName;
				$relation = $this->model->{$name}();
				$model = $relation->getQuery()->getModel();
				
				call_user_func($constraint, $relation);
				
				$this->relations[] = compact("id","name","prefix","relation","model","childs");
				$this->relationLibrary[$fullName] = &$this->relations[ count($this->relations) - 1 ];
			} else {
				$parentName = explode(".", $fullName);
				$name = array_pop($parentName);
				$parent = &$this->relationLibrary[implode(".", $parentName)];
				
				$relation = $parent["model"]->{$name}();
				$model = $relation->getQuery()->getModel();
				
				call_user_func($constraint, $relation);
				
				$parent["childs"][] = compact("id","name","prefix","relation","model","childs");
				$this->relationLibrary[$fullName] = &$parent["childs"][count($parent["childs"])-1];
			}
		}
	}
	
	public function applyRelationshipQuery($relations, $parent = null)
	{
		if (!$parent) {
			$parent = array("id" => $this->model->getTable(), "prefix" => "");
			$this->query->groupBy($this->model->getKeyName());
		}
		
		foreach($relations as $i => $relation) {
			
			$joinCondition = function($join) use ($parent, $relation)  {
				
				if ($relation["relation"] instanceof \Illuminate\Database\Eloquent\Relations\HasOneOrMany) {
					$firstKey = explode( ".", $relation["relation"]->getQualifiedParentKeyName() );
					$firstKey = array_pop( $firstKey );
					$secondKey = $relation["relation"]->getPlainForeignKey();
				} elseif ($relation["relation"] instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
					$firstKey = $relation["relation"]->getForeignKey();
					$secondKey = $relation["relation"]->getOtherKey();
				}
				
				$join->on(
					$parent["prefix"] . $parent["id"] . "." . $firstKey, 
					"=", 
					$relation["prefix"] . $relation["id"] . "." . $secondKey
				);
				
				foreach($relation["relation"]->getQuery()->getQuery()->wheres as $where) {
					
					if (!in_array($where["type"], $this->supportedJoinCondition)) continue;
					
					$join->on(
						$this->getRelationalColumnName($where["column"]),
						$where["operator"],
						is_string($where["value"]) ? $this->getRelationalColumnName($where["column"]) : $where["value"],
						$where["boolean"],
						!is_string($where["value"])
					);
				}
			};
		
			$this->query->leftJoin(
				$relation["relation"]->getRelated()->getTable()." as ".$relation["prefix"].$relation["id"],
				$joinCondition
			);
			
			$this->applyRelationshipQuery($relation["childs"], $relation);
		}
	}
	
	public function getRelationalColumnName($column)
	{
		if (strpos($column, ".") === false) {
			if (in_array($column, $this->model->getFields()))
				return $this->model->getTable() . "." . $column;
			else 
				return $column;
		}
		
		$parentName = explode(".", $column);
		
		if (strtolower($parentName[0]) == strtolower($this->model->getTable()) && count($parentName) == 2)
			return $column;
		
		$column = array_pop($parentName);
		$parentName = implode(".", $parentName);
		
		if ($relation = $this->relationLibrary[$parentName]) 
			return $relation["prefix"] . $relation["id"] . "." .$column;
		
		return $parentName . "." . $column;
	}
	
	public function getColumns($relations, $parent = array())
	{
		$columns = array();
		if (!$parent) {
			foreach($this->model->getFields() as $i => $field)
				if ($this->prepareForPagination && $i == 0)
					$columns[] = $this->query->raw("SQL_CALC_FOUND_ROWS " . $this->model->getTable().".".$field);
				else 
					$columns[] = $this->model->getTable().".".$field;
			
		}
		
		foreach($relations as $i => $relation) {
			$column = $parent;
			foreach($relation["model"]->getFields() as $field)
				$column[] = "IFNULL(".$relation["prefix"].$relation["id"]. "." .$field.",'')";
			$columns[] = $this->query->raw(
					"GROUP_CONCAT(DISTINCT 
						CONCAT(".implode(",'".static::FIELD_SEPARATOR."',", $column).") 
						SEPARATOR '".static::GROUP_SEPARATOR."') 
						as ".$relation["prefix"].$relation["id"]."_fields"
			);
			$referencedParent = array_merge(
				$parent,
				array($relation["prefix"].$relation["id"]. "." .$relation["model"]->getKeyName())
			);
			$columns = array_merge(
				$columns,
				$this->getColumns($relation["childs"], $referencedParent)
			);
		}
		return $columns;
	}
	
	public function applySearch()
	{
		if ($this->search) {
/*
			$this->orWhere(function($query) {
				foreach($this->model->getFields() as $field)
					$query->orWhere($this->model->getTable() .".". $field, "like", "%".$this->search."%");
			});
*/
			foreach($this->model->getFields() as $field)
				$this->query->orHavingRaw($this->model->getTable() .".". $field. " like ". "'%".$this->search."%' COLLATE latin1_swedish_ci");
			foreach($this->relationLibrary as $relation) {
				$this->query->orHavingRaw($relation["prefix"].$relation["id"] . "_fields". " like ". "'%".$this->search."%' COLLATE latin1_swedish_ci");
			}
		}
	}
	
	public function reformatQueryComponents(&$query = null)
	{
		if (!$query) $query = $this->query;
		foreach($this->queryComponents as $component) {
			if (!$query->{$component}) continue;
			
			foreach($query->{$component} as $i => $item) {
				call_user_func_array(array($this, "reformat". $item["type"] ."Query"), array(&$query->{$component}[$i], $component));
			}
		}
	}
	
	public function reformatBasicQuery(&$data, $component = null)
	{
		if ($data["column"]) {
			if (strpos($data["column"], "obs::") === 0) {
				$data["column"] = self::OBSERVER_PREFIX . substr( $data["column"], strlen("obs::") );
			}  else {				
				$data["column"] = $this->getRelationalColumnName( $data["column"] );
			}
		}
		
		if ($data["value"]) {
			if ($data["value"] instanceof Expression) {
				$data["type"] = "raw";
				$data["sql"] = $this->query->raw(
					$data["column"]. " " .
					$data["operator"]. " ".
					$data["value"]
				);
				unset($data["value"]);
			}
		}
	}
	
	public function reformatQuery(&$data, $component = null)
	{
		$this->reformatBasicQuery($data);
	}
	
	public function reformatRawQuery(&$data, $component = null)
	{
		$this->reformatBasicQuery($data);
	}
	
	public function reformatBetweenQuery(&$data, $component = null)
	{
		$this->reformatBasicQuery($data);
	}
	
	public function reformatNestedQuery(&$data, $component = null)
	{
		$this->reformatQueryComponents($data["query"]);
	}
	
	public function reformatExistsQuery(&$data, $component = null)
	{
		$this->reformatBasicQuery($data);
	}
	
	public function reformatNotExistsQuery(&$data, $component = null)
	{
		$this->reformatBasicQuery($data);
	}
	
	public function reformatInQuery(&$data, $component = null)
	{
		$this->reformatBasicQuery($data);
	}
	
	public function reformatNotInQuery(&$data, $component = null)
	{
		$this->reformatBasicQuery($data);
	}
	
	public function reformatNullQuery(&$data, $component = null)
	{
		$this->reformatBasicQuery($data);
	}
	
	public function reformatNotNullQuery(&$data, $component = null)
	{
		$this->reformatBasicQuery($data);
	}
	
	public function reformatColumns($columns)
	{
		foreach($this->query->columns as $i => $column){
			if ($column instanceof Query\Expression) continue;
			
			$this->query->columns[$i] = $this->getRelationalColumnName($this->query->columns[$i]);
		}
	}
	
	public function buildModelsFromRelationshipTree($relations, $results)
	{
		$models = array();
		foreach($results as $i => $result) {
			$attributes = array();
			foreach($this->model->getFields() as $field) 
				$attributes[$field] = $result->{$field};
			$models[$i] = $this->model->newFromBuilder($attributes);
			$models[$i]->setConnection($this->model->getConnectionName());
			
			$this->buildModel($models[$i], $relations, $result);
		}
		
		return $models;
	}
	
	public function buildModel(&$parent, $relations, $result, $parentKey = "")
	{
		foreach($relations as $relation) {
			$fieldSets = array_filter(
				explode(static::GROUP_SEPARATOR, $result->{ $relation["prefix"].$relation["id"]. "_fields" }), 
				function($value) use ($parentKey) {
					return !$parentKey || strpos($value, $parentKey) === 0;
				}
			);
			
			$models = array();
			foreach(array_values($fieldSets) as $i => $fieldSet) {
				if ($model = $this->createModelInstanceFromResult($relation, $fieldSet, $parentKey)) {
					$models[$i] = $model;
					$this->buildModel($models[$i], $relation["childs"], $result, $parentKey ? $parentKey . static::FIELD_SEPARATOR . $models[$i]->getKey() : $models[$i]->getKey());
				}
			}
			
			if ($models) {
				switch(get_class($relation["relation"])) {
					case "Illuminate\Database\Eloquent\Relations\HasOne":
					case "Illuminate\Database\Eloquent\Relations\BelongsTo":
						$collection = array_shift($models);
						break;
					case "Illuminate\Database\Eloquent\Relations\HasMany":
					default:
						$collection = $relation["model"]->newCollection($models);
						break;
				}
			} else unset($collection);
			
			$parent->setRelation($relation["name"], $collection);
		}
	}
		
	public function createModelInstanceFromResult($relation, $fieldSet, $parentKey)
	{
		$fieldSet = explode(static::FIELD_SEPARATOR, $parentKey ? substr($fieldSet, strlen($parentKey) + strlen(static::FIELD_SEPARATOR)) : $fieldSet);
		
		if (array_filter($fieldSet, function($val) { return $val && true;})) {
			$attributes = array();
			foreach($relation["model"]->getFields() as $i => $field) 
				$attributes[$field] = $fieldSet[$i];
				
			$model = $relation["model"]->newFromBuilder($attributes);
			$model->setConnection($relation["model"]->getConnectionName());
			return $model;
		}
		
		return false;
	}
	
	public function addObserverField($function, $columns, $alias)
	{
		if (!is_array($columns)) $columns = array($columns);
		if (isset($this->observer[$alias])) throw new Exception("Observer alias already defined.");
		
		$this->observer[$alias] = compact("function", "columns");
		
		return $this;
	}
	
	public function getObserverColumns()
	{
		$columns = array();
		foreach($this->observer as $alias => $value) {
			
			if ($value["function"] == "SUM") {
				$name = $this->getRelationalColumnName($value["columns"][0]);
				$columns[] = $this->query->raw($value["function"] . "($name) as ".self::OBSERVER_PREFIX.$alias);
			}
		}
		return $columns;
	}
}