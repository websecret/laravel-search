<?php
return [
    'hosts' => array_filter(explode('|', ENV('ELASTICSEARCH_HOSTS', ''))),
    'index' => ENV('ELASTICSEARCH_INDEX', 'index'),
    'indexing' => ENV('ELASTICSEARCH_INDEXING', true),
    'fuzziness' => 2,
    "prefix_length" => 2,
    "max_expansions" => 100,
    "lenient" => true,
];
