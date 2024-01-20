<?php

namespace App\Dataproviders\Filters;
use Illuminate\Database\Eloquent\Model;

class ForeignData
{
    private readonly string $localTable;
    private readonly string $localKey;
    private readonly string $foreignTable;
    private readonly string $foreignKey;
    private ?ForeignData $foreignData;

    public function __construct(string $localModel, string $localKey, string $foreignModel, string $foreignKey, ?ForeignData $foreignData = null) {
        $this->localTable = app($localModel)->getTable();
        $this->localKey = $localKey;
        $this->foreignTable = app($foreignModel)->getTable();
        $this->foreignKey = $foreignKey;
        $this->foreignData = $foreignData;
    }

    public function linkForeignTable($builder) {
        $builder = $builder->join(
            $this->foreignTable,
            sprintf('%s.%s', $this->localTable, $this->localKey),
            '=',
            sprintf('%s.%s', $this->foreignTable, $this->foreignKey)
        );

        if ($this->foreignData !== null) {
            $this->foreignData->linkForeignTable($builder);
        }

        return $builder;
    }

    public function getForeignTableName(): string {
        if ($this->foreignData === null) {
            return $this->foreignTable;
        }

        return $this->foreignData->getForeignTableName();
    }
}
