<?php

namespace App\Dataproviders\Filters;
use Illuminate\Database\Eloquent\Model;

class NumberFilter extends AbstractFilter
{
    protected string $type = 'number';

    protected function getOperators(): array {
        return [
            ['operator' => '=', 'text' => 'is'],
            ['operator' => '!=', 'text' => 'is not'],
            ['operator' => '>', 'text' => 'is higher than'],
            ['operator' => '>=', 'text' => 'is higher or equal to'],
            ['operator' => '<=', 'text' => 'is lower or equal to'],
            ['operator' => '<', 'text' => 'is lower than'],
        ];
    }

    protected function getOptions(): array {
        return [
            'min' => $this->getModel()->min($this->column),
            'max' => $this->getModel()->max($this->column)
        ];
    }
}
