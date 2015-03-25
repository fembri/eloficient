<?php namespace Fembri\Eloficient;

use Illuminate\Container\Container;
use Illuminate\Database\DatabaseManager;
use Illuminate\Events\Dispatcher;

class EloficientManager {
	
	protected $app;
	
	protected $fieldCache;
	
	public function __construct(Container $app, FieldCache $fieldCache)
	{
		$this->app = $app;
		$this->fieldCache = $fieldCache;
	}
	
	public function cacheFields()
	{
		return $this->fieldCache->cache();
	}
	
	public function getFieldScanner()
	{
		return $this->fieldScanner;
	}
	
	public function getFieldCache()
	{
		return $this->fieldCache;
	}
	
	public function bootEloficient(DatabaseManager $database, Dispatcher $dispatcher, FieldCache $fieldCache)
	{
		Model::setConnectionResolver($database);

		Model::setEventDispatcher($dispatcher);
		
		Model::setFieldCache($fieldCache);
	}
}