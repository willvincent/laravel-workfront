<?php namespace willvincent\Workfront;

use Illuminate\Support\ServiceProvider;
use willvincent\Workfront\Workfront;

class WorkfrontServiceProvider extends ServiceProvider {

  /**
   * Indicates if loading of the provider is deferred.
   *
   * @var bool
   */
  protected $defer = true;


  /**
   * Expose config file so it can be published into the app with artisan.
   *
   * @return  void
   */
  public function boot() {
    $this->publishes([
      __DIR__ . '/config/workfront.php' => config_path('workfront.php'),
    ]);
  }


  /**
   * Register the service provider.
   *
   * @return void
   */
  public function register() {
    $this->app->singleton('workfront', function ($app) {

      $config = config('workfront');

      if (!$config) {
        throw new \RunTimeException('Workfront configuration not found. Please run `php artisan vendor:publish`');
      }

      return new Workfront($config);

    });
  }


  /**
   * Get the services provided by the provider.
   *
   * @return  array
   */
  public function provides() {
    return ['workfront'];
  }
}
