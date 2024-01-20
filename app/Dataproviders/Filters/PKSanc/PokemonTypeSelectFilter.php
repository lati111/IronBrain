<?php

namespace App\Dataproviders\Filters\PKSanc;
use App\Dataproviders\Filters\AbstractFilter;
use App\Dataproviders\Filters\ForeignData;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Type;

class PokemonTypeSelectFilter extends AbstractFilter
{
    protected string $type = 'select';

    public function __construct() {
        parent::__construct(new StoredPokemon, 'name',
            new ForeignData(StoredPokemon::class, 'pokemon', Pokemon::class, 'pokemon')
        );
    }

    public function handle($builder, string $operator, string $value) {
        $this->foreignData->linkForeignTable($builder);

        $builder->where(function ($query) use ($operator, $value){
            $query->where('primary_type', $operator, $value)
                  ->orWhere('secondary_type', $operator, $value);
        });

        return $builder;
    }

    protected function getOperators(): array {
        return [
            ['operator' => '=', 'text' => 'is'],
            ['operator' => '!=', 'text' => 'is not'],
        ];
    }

    protected function getOptions(): array {
        return Type::select('name')->get()->pluck($this->column)->toArray();
    }
}
