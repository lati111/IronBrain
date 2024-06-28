<?php

namespace App\Http\Dataproviders\Interfaces;

interface FilterableDataproviderInterface
{
    public function getFilterList(): array;
}
