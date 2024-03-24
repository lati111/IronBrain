<?php

namespace App\Dataproviders\Filters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CustomColumn
{
    private readonly string $selector;
    private readonly string $alias;

    public function __construct(string $selector, string $alias) {
        $this->selector = $selector;
        $this->alias = $alias;
    }

    public function applySelector($builder) {
        return $builder->addSelect(DB::raw(sprintf('%s as %s', $this->selector, $this->alias)));
    }

    public function getAlias(): string {
        return $this->alias;
    }
}
