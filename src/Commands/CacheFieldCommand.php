<?php namespace Fembri\Eloficient\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CacheFieldCommand extends Command {

	/**
	 * Name of the command
	 * @var string
	 */
	protected $name = 'eloficient:cachefield';

	/**
	 * Command description
	 * @var string
	 */
	protected $description = 'Cache eloficient model fields.';

	/**
	 * Path to the modules monifest
	 * @var string
	 */
	protected $manifestPath;

	/**
	 * Execute the console command.
	 * @return void
	 */
	public function fire()
	{
		$this->info('Caching models fields.');
		
		$models = App("eloficient")->cacheFields();
		
		$this->info('Finished! Found '.count($models)." models.");
	}
}
