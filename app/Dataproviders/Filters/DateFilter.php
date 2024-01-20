<?php

namespace App\Dataproviders\Filters;
use Illuminate\Database\Eloquent\Model;

class DateFilter extends AbstractFilter
{
    protected string $type = 'date';

    protected function getOperators(): array {
        return [
            ['operator' => '=', 'text' => 'is'],
            ['operator' => '!=', 'text' => 'is not'],
            ['operator' => '>', 'text' => 'is after'],
            ['operator' => '>=', 'text' => 'is after or on'],
            ['operator' => '<=', 'text' => 'is before or on'],
            ['operator' => '<', 'text' => 'is before'],
        ];
    }

    protected function getOptions(): array {
        return [
            'min' => $this->getModel()->min($this->column),
            'max' => $this->getModel()->max($this->column)
        ];
    }
}
