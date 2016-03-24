<?php namespace Websecret\LaravelSearchable;

interface SearchableInterface
{
    public function searchIndex();
    public function searchDelete();
}
