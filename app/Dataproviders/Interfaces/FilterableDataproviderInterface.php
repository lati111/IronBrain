<?php

namespace App\Dataproviders\Interfaces;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

interface FilterableDataproviderInterface
{
    public function getFilterList(): array;
}
