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
	Websecret\LaravelSearchable\SearchableServiceProvider::class,
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
        //'index' => 'domain_name',
        //'type' => 'articles',
        'fields' => [
            'title' => [
                'weight' => 3,
            ],
            'content',
            'category.title' => [
                'title' => 'category',
                'weight' => 1,
            ],
        ],
        //"fuzziness" => "AUTO",
        //"prefix_length"=> 2,
        //"max_expansions"=> 100,
    ];

}
```


## Searching

Use `search` scope to find models. Result collection will be sorted by score. 

```
    $articles = Article::where('is_active', 1)->search('apple')->get();
```


## Indexing

Models auto indexing on `updated`, `created` and `deleted` events.
You can use `$article->searchIndex();` and `$article->searchDelete();` to manually index or delete from index. Use `Article::searchDeleteAll()` to clear all index by specified model.


## Config

### fuzziness

Fuzzy matching treats two words that are “fuzzily” similar as if they were the same word.

Of course, the impact that a single edit has on a string depends on the length of the string. Two edits to the word hat can produce mad, so allowing two edits on a string of length 3 is overkill. The fuzziness parameter can be set to AUTO, which results in the following maximum edit distances:

* `0` for strings of one or two characters
* `1` for strings of three, four, or five characters
* `2` for strings of more than five characters

Of course, you may find that an edit distance of 2 is still overkill, and returns results that don’t appear to be related. You may get better results, and better performance, with a maximum fuzziness of 1.

### prefix_length

The number of initial characters which will not be “fuzzified”. This helps to reduce the number of terms which must be examined.
 
### max_expansions

The maximum number of terms that the fuzzy query will expand to.