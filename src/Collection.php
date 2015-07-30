<?php namespace Fembri\Eloficient;

use Illuminate\Database\Eloquent\Collection as BaseCollection;

class Collection extends Illuminate\Database\Eloquent\Collection 
{
	/**
	 * Load a set of relationships onto the collection.
	 *
	 * @param  mixed  $relations
	 * @return $this
	 */
	public function load($relations)
	{
		if (count($this->items) > 0)
		{
			if (is_string($relations)) $relations = func_get_args();

			$query = $this->first()->newQuery()->with($relations);

			$this->items = $query->load($this->items);
		}

		return $this;
	}
}