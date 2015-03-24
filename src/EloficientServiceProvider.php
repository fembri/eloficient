<?php namespace Fembri\Eloficient;

use Illuminate\Support\ServiceProvider;
use Fembri\Eloficient\Commands\ScanFieldCommand;

class EloficientServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('fembri/eloficient', 'eloficient', __DIR__);
		
		$this->app['eloficient']->bootEloficient(
			$this->app['db'], 
			$this->app['events'], 
			$this->app['eloficient']->getFieldCache()
		);
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bindShared('eloficient', function($app)
		{
			return new EloficientManager(
				$app,
				new FieldCache($app["config"]->get("eloficient::modelPaths"), $app["config"]->get("eloficient::modelFieldCachePath"))
			);
		});
		
		$this->registerCommands();
	}
	
	public function registerCommands()
	{
		$this->app->bindShared('eloficient.scanfield', function($app)
		{
			return new ScanFieldCommand($app);
		});
		
		$this->commands('eloficient.scanfield');
	}

	public function provides()
	{
		return array("eloficient");
	}
}
