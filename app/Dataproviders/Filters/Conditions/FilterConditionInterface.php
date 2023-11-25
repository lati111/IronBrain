<?php

namespace App\Dataproviders\Filters\Conditions;
use App\Dataproviders\Filters\AbstractFilter;

interface FilterConditionInterface
{
    public function apply($builder);
}
