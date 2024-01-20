<?php

namespace App\Dataproviders\Filters;

class SelectFilter extends AbstractFilter
{
    protected string $type = 'select';

    protected function getOperators(): array {
        return [
            ['operator' => '=', 'text' => 'is'],
            ['operator' => '!=', 'text' => 'is not'],
        ];
    }

    protected function getOptions(): array {
        return $this->getValues($this->getModel()->distinct());
    }
}
