<?php

namespace Tests\Unit\DataProvider\Datatable;

use Tests\Unit\DataProvider\AbstractDataProviderTester;

abstract class AbstractDatatableTester extends AbstractDataProviderTester
{
    private int $page = 3;
    private int $perpage = 10;


    protected function getDefaultFilters() {
        return [
            'page' => $this->page,
            'perpage' => $this->perpage,
        ];
    }

    protected function assertFiltersValid(array $datatable) {
        $this->assertCount($this->perpage, $datatable);
    }

    protected function assertContentValid(string $model, array $datatable, array $columnStructure) {
        foreach ($datatable as $datarow) {
            $qb = $model::select();
            for ($i = 0; $i < count($columnStructure); $i++) {
                if ($columnStructure[$i] === null) {
                    continue;
                }

                $qb->where($columnStructure[$i], $datarow[$i]);
            }
            $this->assertGreaterThanOrEqual(1, $qb->count());
        }
    }
}
