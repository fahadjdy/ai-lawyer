<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Base Eloquent repository. Concrete repositories extend this and implement
 * {@see applyFilters()} to translate request filters into query constraints,
 * keeping query logic out of controllers and services.
 *
 * @template TModel of Model
 *
 * @implements RepositoryContract<TModel>
 */
abstract class BaseRepository implements RepositoryContract
{
    /**
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * Relationships eager-loaded on list/detail queries to prevent N+1.
     *
     * @return array<int, string>
     */
    protected function with(): array
    {
        return [];
    }

    /**
     * @return Builder<TModel>
     */
    protected function query(): Builder
    {
        return $this->model()::query()->with($this->with());
    }

    /**
     * Hook for concrete repositories to apply filtering/sorting.
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query;
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);

        return $this->applyFilters($this->query(), $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return $this->query()->get();
    }

    public function find(int|string $id): ?Model
    {
        return $this->resolveByKey($id)->first();
    }

    public function findOrFail(int|string $id): Model
    {
        return $this->resolveByKey($id)->firstOrFail();
    }

    public function create(array $attributes): Model
    {
        return $this->model()::create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    /**
     * Resolve a record by uuid (preferred external key) or numeric id.
     *
     * @return Builder<TModel>
     */
    protected function resolveByKey(int|string $id): Builder
    {
        $query = $this->query();

        return is_numeric($id)
            ? $query->whereKey($id)
            : $query->where('uuid', $id);
    }
}
