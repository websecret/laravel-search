# Laravel-Searchable

Laravel Eloquent models with Elasticsearch 2


## Installation and Requirements

First, you'll need to require the package with Composer:

```sh
composer require websecret/laravel-search
```

Aftwards, run `composer update` from your command line.

Then, update `config/app.php` by adding an entry for the service provider.

```php
'providers' => [
	// ...
	'Websecret\LaravelSearchable\SearchableServiceProvider',
];
```

Finally, from the command line again, run `php artisan vendor:publish --provider=Websecret\LaravelSearchable\SearchableServiceProvider` to publish 
the default configuration file.


## Updating your Eloquent Models

Your models should implement Searchable's interface and use it's trait. You should 
also define a protected property `$searchable` with any model-specific configurations 
(see [Configuration](#config) below for details):

```php
use Websecret\LaravelSearchable\SearchableTrait;
use Websecret\LaravelSearchable\SearchableInterface;

class Article extends Model implements SearchableInterface
{
	use SearchableTrait;

	protected $searchable = [
        'fields' => [
            'title' => [
                'weight' => 3,
            ],
            'content'
            'category.title' => [
                'title' => 'category',
                'weight' => 1,
            ],
        ],
        "fuzziness" => "AUTO",
        "prefix_length"=> 2,
    ];

}
```