<?php namespace Websecret\LaravelSearchable;

use Illuminate\Support\ServiceProvider;

class SearchableServiceProvider extends ServiceProvider
{

    protected $defer = false;

    public function boot()
    {
        $this->handleConfigs();
    }

    public function register()
    {
        $this->registerEvents();
    }

    private function handleConfigs()
    {
        $configPath = __DIR__ . '/../config/searchable.php';
        $this->publishes([$configPath => config_path('searchable.php')]);
        $this->mergeConfigFrom($configPath, 'searchable');
    }

    public function registerEvents()
    {
        $this->app['events']->listen('eloquent.saved*', function ($event, $models) {
            foreach ($models as $model) {
                if ($model instanceof SearchableInterface) {
                    $model->searchIndex();
                }
            }
        });
        $this->app['events']->listen('eloquent.deleted*', function ($event, $models) {
            foreach ($models as $model) {
                if ($model instanceof SearchableInterface) {
                    $model->searchDelete();
                }
            }
        });
    }
}
