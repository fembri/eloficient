<?php namespace Fembri\Eloficient;

use Illuminate\Support\ServiceProvider;
use Fembri\Eloficient\Commands\CacheFieldCommand;

class EloficientServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->publishes([
			__DIR__.'/config/config.php' => config_path('eloficient.php')
		]);
		
		$this->app['eloficient']->bootEloficient(
			$this->app['db'], 
			$this->app['events'], 
			$this->app['eloficient']->getFieldCache()
		);

		$this->app['db']->statement('SET SESSION group_concat_max_len = 16384');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->mergeConfigFrom(__DIR__.'/config/config.php', 'eloficient');

		$this->app->singleton('eloficient', function($app)
		{
			return new EloficientManager(
				$app,
				new FieldCache(
					$app["config"]->get("eloficient.modelPaths", app_path()), 
					$app["config"]->get("eloficient.modelFieldCachePath", storage_path('eloficient'))
				)
			);
		});
		
		$this->registerCommands();
	}
	
	public function registerCommands()
	{
		$this->app->singleton('eloficient.cachefield', function($app)
		{
			return new CacheFieldCommand($app);
		});
		
		$this->commands('eloficient.cachefield');
	}

	public function provides()
	{
		return array("eloficient", "eloficient.cachefield");
	}
}
