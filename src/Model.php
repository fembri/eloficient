<?php namespace Fembri\Eloficient;

use DateTime;
use ArrayAccess;
use Carbon\Carbon;
use LogicException;
use JsonSerializable;
use Illuminate\Events\Dispatcher;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Contracts\JsonableInterface;
use Illuminate\Support\Contracts\ArrayableInterface;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
//use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Fembri\Eloficient\QueryBuilder;

abstract class Model extends Eloquent {

	/**
	 * The model's fields.
	 *
	 * @var array
	 */
	protected $fields = array();
	
	protected static $fieldCache;

	/**
	 * Get all of the current fields on the model.
	 *
	 * @return array
	 */
	public function getFields()
	{
		if (!$this->fields) {
			$this->fields = $this->getFieldCache()->get(get_called_class());
		}
		return $this->fields;
	}
	
	public function getFieldCache()
	{
		return static::$fieldCache;
	}
	
	public static function setFieldCache($fieldCache)
	{
		return static::$fieldCache = $fieldCache;
	}
	
	public static function field($name)
	{
		return new FieldName($name);
	}

	/**
	 * Set current fields on the model.
	 *
	 * @return void
	 */
	public function setFields($fields)
	{
		$this->fields = $fields;
	}
	
	public function newEloquentBuilder($query)
	{
		return $this->newEloficientBuilder($query);
	}
	
	public function newEloficientBuilder($query)
	{
		return new Builder($query);
	}
	
	protected function newBaseQueryBuilder()
	{
		$conn = $this->getConnection();

		$grammar = $conn->getQueryGrammar();

		return new QueryBuilder($conn, $grammar, $conn->getPostProcessor());
	}
	
	public function load($relations)
	{
		if (is_string($relations)) $relations = func_get_args();

		$query = $this->newQuery()->with($relations);

		$query->load(array($this));
		
		return $this;
	}
	
	public function push()
	{
		if ( ! $this->save()) return false;

		// To sync all of the relationships to the database, we will simply spin through
		// the relationships and save each model via this "push" method, which allows
		// us to recurse into all of these nested relations for the model instance.
		foreach ($this->relations as $models)
		{
			foreach (Collection::make($models) as $model)
			{
				if ( ! $model->push()) return false;
			}
		}

		return true;
	}
	
	public function newCollection(array $models = array())
	{
		return new Collection($models);
	}

}
