<?php

namespace App\Http\Dataproviders\Filters;
use App\Dataproviders\Filters\Conditions\FilterConditionInterface;
use App\Dataproviders\Filters\ForeignData;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractFilter
{
    protected string $type;
    protected string|CustomColumn $column;
    protected Model $model;
    protected ?ForeignData $foreignData;
    protected array $conditions = [];

    public function __construct(Model $model, string|CustomColumn $column, ?ForeignData $foreignData = null) {
        $this->model = $model;
        $this->column = $column;
        $this->foreignData = $foreignData;
    }

    public function handle($builder, string $operator, string $value) {
        if ($this->foreignData !== null) {
            $this->foreignData->linkForeignTable($builder);
        }

        if ($this->column instanceof CustomColumn) {
            $builder = $this->column->applySelector($builder);
            $builder->having($this->column->getAlias(), $operator, $value);
            return;
        }

        $builder->where($this->column, $operator, $value);
    }

    protected function getModel() {
        $model = $this->model::select();
        if ($this->column instanceof CustomColumn) {
            $model = $this->column->applySelector($model);
        } else if ($this->foreignData !== null) {
            $model = $this->model->addSelect(sprintf('%s.%s', $this->foreignData->getForeignTableName(), $this->column));
        } else {
            $model =  $this->model->addSelect($this->column);
        }

        if ($this->foreignData !== null) {
            $model = $this->foreignData->linkForeignTable($model);
        }

        return $this->applyConditions($model);
    }

    private function applyConditions($builder) {
        foreach ($this->conditions as $condition) {
            $builder = $condition->apply($builder);
        }

        return $builder;
    }

    public function addCondition(FilterConditionInterface $condition): void {
        $this->conditions[] = $condition;
    }

    public function getJson(): array {
        return [
            'type' => $this->type,
            'operators' => $this->getOperators(),
            'options' => $this->getOptions()
        ];
    }

    abstract protected function getOperators(): array;
    abstract protected function getOptions(): array;

    protected function getValues($builder) {
        if ($this->column instanceof CustomColumn) {
            return $builder->get()->pluck($this->column->getAlias())->toArray();
        }

        return $builder->get()->pluck($this->column)->toArray();
    }
}
