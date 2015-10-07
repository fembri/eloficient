<?php namespace Fembri\Eloficient;

class FieldName {
	
	protected $field = "";
	
	public function __construct($name)
	{
		$this->setName($name);
	}
	
	public function getName()
	{
		return $this->field;
	}
	
	public function setName($name)
	{
		$this->field = $name;
	}
}