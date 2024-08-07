<?php

namespace App\Http\Dataproviders\Filters\PKSanc;

use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Type;
use Illuminate\Database\Eloquent\Builder;
use Lati111\LaravelDataproviders\Exceptions\DataproviderException;
use Lati111\LaravelDataproviders\Filters\AbstractFilter;
use Lati111\LaravelDataproviders\Filters\ForeignTable;

class StoredPokemonTypeSelectFilter extends AbstractFilter
{
    /** { @inheritdoc } */
    protected string $type = 'select';

    public function __construct() {
        parent::__construct(new StoredPokemon, 'name',
            new ForeignTable(StoredPokemon::class, 'pokemon', Pokemon::class, 'pokemon')
        );
    }

    /** { @inheritdoc } */
    public function handle(Builder $builder, string $operator, mixed $value): Builder {
        if ($this->validateOperator($operator) === false) {
            throw new DataproviderException(sprintf('Operator %s does not exist on filter %s', $operator, self::class));
        }

        $builder->where(function($query) use ($operator, $value) {
            $query
                ->where(Pokemon::getTableName().".primary_type", $operator, $value)
                ->orWhere(Pokemon::getTableName().".secondary_type", $operator, $value);
        });

        return $builder;
    }

    /** { @inheritdoc } */
    protected function getOperators(): array {
        return [
            ['operator' => '=', 'text' => 'is'],
            ['operator' => '!=', 'text' => 'is not'],
        ];
    }

    /** { @inheritdoc } */
    protected function getOptions(): array {
        return Type::select('name')->get()->pluck($this->column)->toArray();
    }
}
