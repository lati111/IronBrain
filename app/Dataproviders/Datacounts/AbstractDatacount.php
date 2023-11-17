<?php

namespace App\Dataproviders\Datacounts;

use App\Dataproviders\AbstractDatalist;
use Illuminate\Http\Request;

abstract class AbstractDatacount extends AbstractDatalist
{
    protected function getCount(Request $request, $builder, bool $pagination = true) {
        $count = $this->applyTableFilters($request, $builder, $pagination)->count();

        if ($pagination === true) {
            $perpage = $request->get('perpage', 10);

            $pages = $count / $perpage;
            if ($count % $perpage !== 0) {
                $pages++;
            }

            return $pages;
        }

        return $count;
    }
}
