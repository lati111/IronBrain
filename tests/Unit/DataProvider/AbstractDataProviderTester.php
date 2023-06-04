<?php

namespace Tests\Unit\DataProvider;

use Tests\Unit\AbstractUnitTester;

abstract class AbstractDataProviderTester extends AbstractUnitTester
{
    protected function assertContentValid(string $model, array $data, array $columnStructure, bool $associative = false) {
        foreach ($data as $row) {
            $qb = $model::select();
            for ($i = 0; $i < count($columnStructure); $i++) {
                if ($columnStructure[$i] === null) {
                    continue;
                }

                if ($associative === true) {
                    $column = $columnStructure[$i];
                    $qb->where($column, $row[$column]);
                } else {
                    $qb->where($columnStructure[$i], $row[$i]);
                }
            }
            $this->assertGreaterThanOrEqual(1, $qb->count());
        }
    }
}
