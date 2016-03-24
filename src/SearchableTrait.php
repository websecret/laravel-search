<?php namespace Websecret\LaravelSearchable;

use Elasticsearch\ClientBuilder;

trait SearchableTrait
{

    private static $elasticsearch;
    private static $config;
    private static $searchedItems = [];

    public function __construct()
    {
        self::$elasticsearch = ClientBuilder::create()->build();
        self::$config = $this->getSearchableConfig();
    }

    public function scopeSearch($query, $search = "")
    {
        $items = $this->getItems($search);
        self::$searchedItems = $items;
        return $query->whereIn('id', array_pluck(array_get($items, 'hits.hits', []), '_id'));
    }

    public static function hydrate(array $items, $connection = null)
    {
        return parent::hydrate($items, $connection)->each(function($item) {
            $hitItem = array_first(array_get(static::$searchedItems, 'hits.hits', []), function($i, $hitItem) use($item) {
                return $hitItem['_id'] == $item->id;
            });
            $item->_score = array_get($hitItem, '_score', 0);
        })->sortByDesc('_score');
    }

    protected function getItems($search)
    {
        if ($fields = array_get(static::$config, 'fields')) {
            $matchFields = [];
            $match = [
                'query' => $search,
                'fields' => $matchFields,
                "fuzziness" => array_get(static::$config, 'fuzziness'),
                "prefix_length"=> array_get(static::$config, 'prefix_length'),
            ];
            foreach ($fields as $fieldName => $fieldArray) {
                if(is_array($fieldArray)) {
                    $fieldTitle = array_get($fieldArray, 'title', $fieldName);
                } else {
                    $fieldName = $fieldArray;
                    $fieldArray = [];
                    $fieldTitle = $fieldName;
                }
                $fieldTitle = str_replace('.', '_', $fieldTitle);
                if(($weight = array_get($fieldArray, 'weight')) !== null) {
                    $fieldTitle = $fieldTitle.'^'.$weight;
                }
                $match['fields'][] = $fieldTitle;
            }
            $query = [
                'multi_match' => $match,
            ];
        } else {
            $query = [
                'query_string' => [
                    'query' => $search,
                ],
            ];
        }
        $items = self::$elasticsearch->search([
            'index' => array_get(self::$config, 'index'),
            'type' => array_get(self::$config, 'type'),
            'body' => [
                'query' => $query,
            ]
        ]);

        return $items;
    }

    protected function getSearchableConfig()
    {
        $defaults = app('config')->get('searchable');
        if (property_exists($this, 'searchable')) {
            $defaults = array_merge($defaults, $this->searchable);
        }
        if (!array_get($defaults, 'type')) {
            $defaults['type'] = str_plural(snake_case(class_basename(self::class)));
        }

        return $defaults;
    }

    protected static function getBody($model)
    {
        if ($fields = array_get(static::$config, 'fields')) {
            $body = [];
            foreach ($fields as $fieldName => $fieldArray) {
                if(is_array($fieldArray)) {
                    $fieldTitle = array_get($fieldArray, 'title', $fieldName);
                } else {
                    $fieldName = $fieldArray;
                    $fieldTitle = $fieldName;
                }
                $fieldTitle = str_replace('.', '_', $fieldTitle);
                $body = array_add($body, $fieldTitle, static::getBodyKey($model, $fieldName));
            }
        } else {
            $body = $model->toArray();
        }
        return $body;
    }

    protected static function getBodyKey($model, $key)
    {
        if (isset($model->{$key})) {
            return $model->{$key};
        }

        $object = $model;
        foreach (explode('.', $key) as $segment) {
            if (!is_object($object) || !$tmp = $object->{$segment}) {
                return null;
            }

            $object = $object->{$segment};
        }

        return $object;
    }

    public function searchIndex()
    {
        static::$elasticsearch->index([
            'index' => array_get(self::$config, 'index'),
            'type' => array_get(self::$config, 'type'),
            'id' => $this->id,
            'body' => self::getBody($this),
        ]);
    }

    public function searchDelete()
    {
        static::$elasticsearch->delete([
            'index' => array_get(self::$config, 'index'),
            'type' => array_get(self::$config, 'type'),
            'id' => $this->id
        ]);
    }

    public static function searchDeleteAll()
    {
        static::$elasticsearch->indices()->deleteMapping([
            'index' => array_get(self::$config, 'index'),
            'type' => array_get(self::$config, 'type'),
        ]);
    }

}