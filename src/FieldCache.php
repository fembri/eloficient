<?php namespace Fembri\Eloficient;

use ReflectionClass;

class FieldCache 
{
	protected $fields = array();
	
	protected $modelFieldPath;
	
	protected $modelPaths = array();
	
	public function __construct($modelPaths, $modelFieldPath)
	{
		$this->modelFieldPath = $modelFieldPath;
		$this->modelPaths = $modelPaths;
		
		if (!file_exists($this->modelFieldPath)) mkdir($this->modelFieldPath);
	}
	
	public function set($models, $fields)
	{
		if (!is_array($models)) $models = array($models => $fields);
		foreach($models as $model => $fields)
			$this->fields[$model] = $fields;
	}
	
	public function get($model)
	{
		if (!$this->has($model)) {
			$this->set($model, $this->loadFieldFromFile($model));
		}
		return $this->fields[$model];
	}
	
	public function has($model)
	{
		return isset($this->fields[$model]);
	}
	
	public function remove($models)
	{
		if (!is_array($models)) $models = array($models);
		
		foreach($models as $model) unset($this->fields[$model]);
	}
	
	public function cache()
	{
		$paths = array();
		foreach($this->modelPaths as $modelPath) {
			$paths[$modelPath] = glob($modelPath."/*.php");
			if (!$paths[$modelPath]) $paths[$modelPath] = array();
		}
		
		$models = array();
		foreach ($paths as $path => $files) {
			foreach ($files as $file) {
				$models = array_merge($models, $this->getEloficientModels($file));
			}
		}
		
		foreach($models as $model) {
			$obj = new ReflectionClass($model);
			if ($obj->isSubClassOf('\Fembri\Eloficient\Model') === false) continue;
			$obj = new $model;
			file_put_contents(
				$this->formatFieldFileName($this->modelFieldPath, $model),
				json_encode( $obj->getConnection()->select("SHOW COLUMNS FROM `".$obj->getTable()."`") )
			);
		}
		
		return $models;
	}
	
	private function getEloficientModels($file)
	{
		$models = array();
		$namespace = "";
		
		$tokens = token_get_all(@file_get_contents($file));
		$tokenLength = count($tokens);
		for ($i = 0; $i < $tokenLength; $i++) {
			if (!is_array($tokens[$i])) continue;
			
			if ($tokens[$i][0] === T_NAMESPACE) {
				$namespace = "";
				for($i += 2; $i < $tokenLength; $i++) {
					if ($tokens[$i] == ";") break;
					$namespace .= $tokens[$i][1];
				}
			} elseif ($tokens[$i][0] === T_CLASS && is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_STRING) {
				$i += 2;
				$models[] = $namespace ? $namespace."\\".$tokens[$i][1] : $tokens[$i][1];
			}
		}
		return $models;
	}
	
	public function loadFieldFromFile($model)
	{
		$fields = json_decode(@file_get_contents($this->formatFieldFileName($this->modelFieldPath, $model)));
		
		if (is_array($fields)) foreach($fields as $i => $field) {
			$fields[$i] = $field->Field;
		}
		if (!$fields) $fields = array();
		return $fields;
	}
	
	public function formatFieldFileName($path, $className)
	{
		return $path."/".str_replace("\\", "", $className).".json";
	}
	
	public function getModelFieldPath()
	{
		return $this->modelFieldPath;
	}
	
	public function setModelFieldPath(String $path)
	{
		return $this->modelFieldPath = $path;
	}
}