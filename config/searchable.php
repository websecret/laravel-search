<?php
return [
    'hosts' => explode('|', ENV('ELASTICSEARCH_HOSTS', '')),
    'index' => ENV('ELASTICSEARCH_INDEX', 'index'),
    'fuzziness' => 2,
    "prefix_length" => 2,
    "max_expansions" => 100,
];