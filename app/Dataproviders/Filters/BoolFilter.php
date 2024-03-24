<?php

namespace App\Dataproviders\Filters;
use Illuminate\Database\Eloquent\Model;

class BoolFilter extends AbstractFilter
{
    protected string $type = 'bool';
    private string $operatorTypes = '';

    public function __construct(string $operatorType, Model $model, string|CustomColumn $column, ?ForeignData $foreignData = null) {
        $this->operatorTypes = $operatorType;
        parent::__construct($model, $column, $foreignData);
    }

    public function handle($builder, string $operator, string $value) {
        parent::handle($builder, $operator, true);
    }

    protected function getOperators(): array {
        switch($this->operatorTypes) {
            default:
            case 'is':
                return [
                    ['operator' => '=', 'text' => 'is'],
                    ['operator' => '!=', 'text' => 'is not'],
                ];
            case 'has':
                return [
                    ['operator' => '=', 'text' => 'has'],
                    ['operator' => '!=', 'text' => 'does not have'],
                ];
            case 'can':
                return [
                    ['operator' => '=', 'text' => 'can'],
                    ['operator' => '!=', 'text' => 'can not'],
                ];
        }

    }

    protected function getOptions(): array {
        return [false, true];
    }
}
