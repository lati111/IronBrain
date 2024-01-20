<?php

namespace App\Dataproviders\Filters\Conditions;
use App\Dataproviders\Filters\AbstractFilter;

class IsConditionFilter implements FilterConditionInterface
{
    private string $column;
    private string $value;

    public function __construct(string $column, string $value) {
        $this->column = $column;
        $this->value = $value;
    }

    public function apply($builder) {
        return $builder->where($this->column, $this->value);
    }
}
