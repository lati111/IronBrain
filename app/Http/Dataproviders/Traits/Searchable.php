<?php

namespace App\Http\Dataproviders\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait Searchable
{
    protected function applySearch(Request $request, Builder|HasMany $builder, array $searchfields): Builder|HasMany
    {
        $validator = Validator::make($request->all(), [
            "search" => "string|nullable"
        ]);

        if ($validator->fails() === false) {
            $searchterm = $request->get("search");
            $builder->where(function($query) use ($searchfields, $searchterm) {
                foreach($searchfields as $searchfield) {
                    $query->orWhere($searchfield, "LIKE", '%'.$searchterm.'%');
                }
            });
        }

        return $builder;
    }
}
