<?php namespace Fembri\Eloficient;

use Closure;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class Builder extends EloquentBuilder {
	
	const RELATION_PREFIX = "rel";
	const FIELD_SEPARATOR = "#";
	const GROUP_SEPARATOR = ",";
	
	/**
	 * Create a new Eloficient builder instance.
	 *
	 * @param  \Illuminate\Database\Eloquent\Builder  $query
	 * @return void
	 */

	public function load($columns = array("*"))
	{
		if (!$this->eagerLoad) return $this->get($columns);
		
		$relations = $this->buildRelationshipTree();
		
		$this->applyRelationshipQuery($relations);
		
		$results = $this->query->get($this->getColumns($relations));
		
		$models = $this->buildModelsFromRelationshipTree($relations, $results);
		
		return $this->model->newCollection($models);
	}
	
	public function buildRelationshipTree()
	{
		$results = array();
		$library = array();
		$prefix = static::RELATION_PREFIX;
		$childs = array();
		
		foreach($this->eagerLoad as $fullName => $constraint) {
			$id = count($library) + 1;
				
			if (strpos($fullName, ".") === false) {
				$name = $fullName;
				$relation = $this->model->{$name}();
				$model = $relation->getQuery()->getModel();
				
				$results[] = compact("id","name","prefix","relation","model","childs");
				$library[$fullName] = &$results[count($results)-1];
			} else {
				$parentName = explode(".", $fullName);
				$name = array_pop($parentName);
				$parent = &$library[implode(".", $parentName)];
				
				$relation = $parent["model"]->{$name}();
				$model = $relation->getQuery()->getModel();
				
				$parent["childs"][] = compact("id","name","prefix","relation","model","childs");
				$library[$fullName] = &$parent["childs"][count($parent["childs"])-1];
			}
		}
		return $results;
	}
	
	public function applyRelationshipQuery($relations, $parent = null)
	{
		if (!$parent) {
			$parent = array("id" => $this->model->getTable(), "prefix" => "");
			$this->query->groupBy($this->model->getKeyName());
		}
		
		foreach($relations as $i => $relation) {
			if ($relation["relation"] instanceof \Illuminate\Database\Eloquent\Relations\HasOneOrMany) {
				$firstKey = explode(".", $relation["relation"]->getQualifiedParentKeyName());
				$firstKey = array_pop($firstKey);
				$secondKey = $relation["relation"]->getPlainForeignKey();
			} elseif ($relation["relation"] instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
				$firstKey = $relation["relation"]->getForeignKey();
				$secondKey = $relation["relation"]->getOtherKey();
			}
			
			$this->query->leftJoin(
				$relation["relation"]->getRelated()->getTable()." as ".$relation["prefix"].$relation["id"],
				$parent["prefix"].$parent["id"].".".$firstKey, "=", $relation["prefix"].$relation["id"].".".$secondKey
			);
			
			$this->applyRelationshipQuery($relation["childs"], $relation);
		}
	}
	
	public function getColumns($relations, $parent = array())
	{
		$columns = array();
		if (!$parent) {
			foreach($this->model->getFields() as $field) 
				$columns[] = $this->model->getTable().".".$field;
		}
		
		foreach($relations as $i => $relation) {
			$column = $parent;
			foreach($relation["model"]->getFields() as $field)
				$column[] = $relation["prefix"].$relation["id"]. "." .$field;
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
	
	public function buildModelsFromRelationshipTree($relations, $results)
	{
		$models = array();
		foreach($results as $result) {
			$key = $result[$this->model->getKeyName()];
			if (!isset($models[$key])) {
				$attributes = array();
				foreach($this->model->getFields() as $field) 
					$attributes[$field] = $result[$field];
				$models[$key] = $this->model->newFromBuilder($attributes);
				$models[$key]->setConnection($this->model->getConnectionName());
			}
			$this->buildModel($models[$key], $relations, $result);
		}
		
		return array_values($models);
	}
	
	public function buildModel(&$parent, $relations, $result, $parentKey = "")
	{
		foreach($relations as $relation) {
			$fieldSets = array_filter(
				explode(static::GROUP_SEPARATOR, $result[ $relation["prefix"].$relation["id"]. "_fields" ]), 
				function($value) use ($parentKey) {
					return !$parentKey || strpos($value, $parentKey) === 0;
				}
			);
			
			$models = array();
			foreach(array_values($fieldSets) as $i => $fieldSet) {
				$models[$i] = $this->createModelInstanceFromResult($relation, $fieldSet, $parentKey);
				$this->buildModel($models[$i], $relation["childs"], $result, $parentKey ? $parentKey."#".$models[$i]->getKey() : $models[$i]->getKey());
			}
			
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
			
			$parent->setRelation($relation["name"], $collection);
		}
	}
		
	public function createModelInstanceFromResult($relation, $fieldSet, $parentKey)
	{
		$fieldSet = explode(static::FIELD_SEPARATOR, $parentKey ? substr($fieldSet, strlen($parentKey) + 1) : $fieldSet);
		
		$attributes = array();
		foreach($relation["model"]->getFields() as $i => $field) 
			$attributes[$field] = $fieldSet[$i];
			
		$model = $relation["model"]->newFromBuilder($attributes);
		$model->setConnection($relation["model"]->getConnectionName());
		return $model;
	}
}
