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
        $this->app['events']->listen('eloquent.created*', function ($model) {
            if ($model instanceof SearchableInterface) {
                if($model::$indexingEnabled && $model::$indexSearchOnCreate) {
                    $model->searchIndex();
                }
            }
        });
        $this->app['events']->listen('eloquent.updated*', function ($model) {
            if ($model instanceof SearchableInterface) {
                if($model::$indexingEnabled && $model::$indexSearchOnUpdate) {
                    $model->searchIndex();
                }
            }
        });
        $this->app['events']->listen('eloquent.deleted*', function ($model) {
            if ($model instanceof SearchableInterface) {
                if($model::$indexingEnabled && $model::$indexSearchOnDelete) {
                    $model->searchDelete();
                }
            }
        });
    }
}
