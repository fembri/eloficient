<?php namespace Creolab\LaravelModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Composer;
use Illuminate\Foundation\Application;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
* Scan available modules
* @author Boris Strahija <bstrahija@gmail.com>
*/
class CacheFieldCommand extends AbstractCommand {

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
		$this->info('Caching models fields');
		
		$this->app["eloficient"]->cacheFields();
		
		$this->info('Finished!');
	}
}
