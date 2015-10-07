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
		$this->package('fembri/eloficient', 'eloficient', __DIR__);
		
		$this->app['eloficient']->bootEloficient(
			$this->app['db'], 
			$this->app['events'], 
			$this->app['eloficient']->getFieldCache()
		);
		$this->app['db']->setQueryGrammar(new MysqlGrammar);
		$this->app['db']->statement('SET SESSION group_concat_max_len = 16384');
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
		$this->app->bindShared('eloficient.cachefield', function($app)
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
