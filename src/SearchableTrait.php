<?php namespace Websecret\LaravelSearchable;

use Elasticsearch\ClientBuilder;

trait SearchableTrait
{

    private static $elasticsearch;
    private static $config;
    private static $searchedItems = [];

    public static $indexingEnabled;
    public static $indexSearchOnCreate = true;
    public static $indexSearchOnUpdate = true;
    public static $indexSearchOnDelete = true;

    private $_score = null;

    public function __construct(array $attributes = [])
    {
        self::$config = $this->getSearchableConfig();
        $hosts = array_get(self::$config, 'hosts', []);
        if (!is_array($hosts) || empty($hosts)) {
            self::$elasticsearch = ClientBuilder::create()->build();
        } else {
            self::$elasticsearch = ClientBuilder::create()->setHosts($hosts)->build();
        }
        self::$indexingEnabled = array_get(static::$config, 'indexing', true);
        parent::__construct($attributes);
    }

    public function scopeSearch($query, $search = "", $options = [], $useTableInQuery = false)
    {
        if (!self::$indexingEnabled) {
            return $query;
        }
        $primaryKey = $this->primaryKey;
        $column = $primaryKey;
        if ($useTableInQuery) {
            $column = $this->getTable() . '.' . $column;
        }
        if (!is_array($search)) {
            $search = [$search];
        }
        $search = array_filter($search);
        $ids = [];
        foreach ($search as $value) {
            $ids = array_merge($ids, $this->getSearchIds($value, $options));
        }
        $ids = array_unique($ids);
        return $query->whereIn($column, $ids);
    }

    protected function getSearchIds($search = "", $options = [])
    {
        $items = $this->getItems($search, $options);
        self::$searchedItems = $items;
        $primaryKey = $this->primaryKey;
        return array_pluck(array_get($items, 'hits.hits', []), '_' . $primaryKey);
    }

    public static function hydrate(array $items, $connection = null)
    {
        $items = parent::hydrate($items, $connection);
        if (count(static::$searchedItems)) {
            $items = $items->each(function (&$item) {
                $hitItem = array_first(array_get(self::$searchedItems, 'hits.hits', []), function ($i, $hitItem) use ($item) {
                    return $hitItem['_id'] == $item->id;
                });
                $item->setAttribute('_score', array_get($hitItem, '_score', 0));
            })->sortByDesc('_score');
        }
        return $items;
    }

    protected function getItems($search, $options = [])
    {
        $search = mb_strtolower($search);
        if (array_get($options, 'wildcard', true)) {
            if (!ends_with($search, '*')) {
                $search = $search . '*';
            }
            if (!starts_with($search, '*')) {
                $search = '*' . $search;
            }
        }
        $variants = $this->getSearchVariants($search);
        if ($fields = array_get(static::$config, 'fields')) {
            $matches = [];
            foreach ($variants as $key => $variant) {
                $variant = addcslashes($variant, '.?|{}[]()"\\/');
                $matchFields = [];
                $match = [
                    'query' => $variant,
                    'fields' => $matchFields,
                    "fuzziness" => array_get(static::$config, 'fuzziness'),
                    "prefix_length" => array_get(static::$config, 'prefix_length'),
                    "max_expansions" => array_get(static::$config, 'max_expansions'),
                    "lenient" => array_get(static::$config, 'lenient', true),
                    "boost" => ($key == 0) ? 0.1 : 0,
                ];
                foreach ($fields as $fieldName => $fieldArray) {
                    if (is_array($fieldArray)) {
                        $fieldTitle = array_get($fieldArray, 'title', $fieldName);
                    } else {
                        $fieldName = $fieldArray;
                        $fieldArray = [];
                        $fieldTitle = $fieldName;
                    }
                    $fieldTitle = str_replace('.', '_', $fieldTitle);
                    if (($weight = array_get($fieldArray, 'weight')) !== null) {
                        $fieldTitle = $fieldTitle . '^' . $weight;
                    }
                    $match['fields'][] = $fieldTitle;
                }
                if (mb_strlen($variant) > 5) {
                    $matches[] = [
                        'multi_match' => $match,
                    ];
                }
                $match['boost'] = ($key == 0) ? 5 : 0;
                $matches[] = [
                    'query_string' => array_except($match, ['prefix_length', 'max_expansions']),
                ];
            }
            $query = [
                'bool' => [
                    'should' => $matches,
                ],
            ];
        } else {
            $query = [
                'query_string' => [
                    'query' => $search,
                ],
            ];
        }
        $body = [
            'query' => $query,
        ];
        $items = self::$elasticsearch->search([
            'index' => array_get(self::$config, 'index'),
            'type' => array_get(self::$config, 'type'),
            'size' => array_get(self::$config, 'size'),
            'body' => $body,
        ]);

        return $items;
    }

    protected function getSearchVariants($search)
    {
        $chars = [
            'q' => 'й',
            'w' => 'ц',
            'e' => 'у',
            'r' => 'к',
            't' => 'е',
            'y' => 'н',
            'u' => 'г',
            'i' => 'ш',
            'o' => 'щ',
            'p' => 'з',
            '[' => 'х',
            '{' => 'Х',
            ']' => 'ъ',
            '}' => 'Ъ',
            '`' => 'ё',
            '~' => 'Ё',
            'a' => 'ф',
            's' => 'ы',
            'd' => 'в',
            'f' => 'а',
            'g' => 'п',
            'h' => 'р',
            'j' => 'о',
            'k' => 'л',
            'l' => 'д',
            ';' => 'ж',
            ':' => 'Ж',
            "'" => 'э',
            '"' => 'Э',
            'z' => 'я',
            'x' => 'ч',
            'c' => 'с',
            'v' => 'м',
            'b' => 'и',
            'n' => 'т',
            'm' => 'ь',
            ',' => 'б',
            '<' => 'Б',
            '.' => 'ю',
            '>' => 'Ю',
        ];
        $variants = [$search];
        $words = explode(' ', $search);
        $wordsCount = count($words);
        $variantsCount = pow(2, $wordsCount) - 1;
        $variantsArray = [];
        for ($w = 0; $w < $variantsCount; $w++) {
            $takeCount = floor($w / $wordsCount) + 1;
            for ($r = 0; $r < $takeCount; $r++) {
                $off = $r + floor($w % $wordsCount);
                if ($off >= $wordsCount) {
                    $off = $off - $wordsCount;
                }
                $variantsArray[$w][] = (int)$off;
            }
        }
        foreach ($variantsArray as $variantArray) {
            $string = '';
            foreach ($words as $workKey => $word) {
                if (in_array($workKey, $variantArray)) {
                    foreach (preg_split('//u', $word, null, PREG_SPLIT_NO_EMPTY) as $letter) {
                        if ($letter == '*') {
                            $string .= $letter;
                        } else {
                            if (isset($chars[$letter])) {
                                $string .= $chars[$letter];
                            } else {
                                $key = array_search($letter, $chars);
                                if ($key === false) {
                                    $letterLower = mb_strtolower($letter);
                                    $key = array_search($letterLower, $chars);
                                }
                                if ($key === false) {
                                    $string .= $letter;
                                } else {
                                    $string .= $key;
                                }
                            }
                        }
                    }
                } else {
                    $string .= $word;
                }
                $string .= ' ';
            }
            $string = trim($string);
            $variants[] = $string;
        }
        return array_unique($variants);
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
                if (is_array($fieldArray)) {
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

    public function searchIndex($additional = [])
    {
        static::$elasticsearch->index([
            'index' => array_get(self::$config, 'index'),
            'type' => array_get(self::$config, 'type'),
            'id' => $this->id,
            'body' => array_merge(self::getBody($this), $additional),
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
