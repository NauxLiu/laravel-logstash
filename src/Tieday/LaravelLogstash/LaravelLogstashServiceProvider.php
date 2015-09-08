<?php
namespace Tieday\LaravelLogstash;

use Illuminate\Cache\RedisStore;
use Monolog\Logger;
use Monolog\Handler\RedisHandler;
use Monolog\Formatter\LogstashFormatter;
use Illuminate\Log\Writer;
use Illuminate\Support\ServiceProvider;
use Config;

class LaravelLogstashServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->package('naux/laravel-logstash', 'laravel-logstash');

        $redis_connection = Config::get('laravel-logstash::redis_connection');
        $environment_tag = Config::get('laravel-logstash::environment_tag');

        $cacheStore = $this->app->make('cache.store')->getStore();

        $cacheStore->setConnection($redis_connection);

        if ($cacheStore instanceof RedisStore) {
            $redis_key = Config::get('laravel-logstash::redis_key');
            $application_name = Config::get('laravel-logstash::application_name');

            $redisClient = $this->app->make('cache.store')->getStore()->connection();
            $redisHandler = new RedisHandler($redisClient, $redis_key);
            $formatter = new LogstashFormatter($application_name);
            $redisHandler->setFormatter($formatter);

            $logger = new Writer(
                new Logger($environment_tag, [$redisHandler])
            );
        } else {
            $logger = new Writer(
                new Logger($this->app['env']), $this->app['events']
            );
        }

        $this->app->instance('log', $logger);

        // If the setup Closure has been bound in the container, we will resolve it
        // and pass in the logger instance. This allows this to defer all of the
        // logger class setup until the last possible second, improving speed.
        if (isset($this->app['log.setup'])) {
            call_user_func($this->app['log.setup'], $logger);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['log'];
    }
}
