<?php

namespace App\Service\Traits;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

trait PreloadsModels
{
    /** @var array<string, Collection> The preloaded models, keyed by cache name */
    private array $preloadedModels = [];

    /**
     * Gets all models of a type in one query, so they don't have to be looked up one by one
     * @param class-string<Model> $model The model to preload
     * @param string|Closure(Model): string $keyBy The column or callback to key the models by
     * @param ?string $name The name of the cache, needed when preloading the same model with different keys
     * @return Collection Returns the models, keyed by the given key
     */
    protected function preloaded(string $model, string|Closure $keyBy, ?string $name = null): Collection
    {
        return $this->preloadedModels[$name ?? $model] ??= $model::all()->keyBy($keyBy);
    }

    /**
     * Clears all preloaded models, so they are loaded again when next needed
     */
    public function clearPreloaded(): void
    {
        $this->preloadedModels = [];
    }
}
