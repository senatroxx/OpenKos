<?php

namespace App\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\Sorts\Sort;

class Table
{
    protected array $columns = [];

    protected array $filters = [];

    protected string $defaultSort = 'name';

    protected array $perPageOptions = [10, 15, 25, 50];

    protected int $defaultPerPage = 15;

    public static function make(): self
    {
        return new self;
    }

    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    public function filters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    public function defaultSort(string $sort): self
    {
        $this->defaultSort = $sort;

        return $this;
    }

    public function perPageOptions(array $options): self
    {
        $this->perPageOptions = $options;

        return $this;
    }

    public function paginate(Builder $query, Request $request, string $dataKey): array
    {
        $search = $this->applySearch($query, $request);

        $perPage = $this->resolvePerPage($request);

        $sortParam = $this->resolveSort($request);

        $synthetic = Request::create('', 'GET', [
            'sort' => $sortParam,
            'filter' => $this->extractFilterValues($request),
        ]);

        $spatie = QueryBuilder::for($query, $synthetic)
            ->defaultSort($this->defaultSort)
            ->allowedSorts(...$this->buildSorts())
            ->allowedFilters(...$this->buildFilters());

        $paginator = $spatie->paginate($perPage);

        $table = [
            'columns' => array_map(fn (Column $c) => $c->toArray(), $this->columns),
            'filters' => array_map(fn (Filter $f) => $f->toArray(), $this->filters),
        ];

        $meta = [
            $dataKey => $paginator,
            'sort' => $request->query('sort', $this->defaultSort),
            'search' => $search,
            'per_page' => $perPage,
            'table' => $table,
        ];

        foreach ($this->filters as $filter) {
            $meta[$filter->key] = $request->query($filter->key, '');
        }

        return $meta;
    }

    protected function applySearch(Builder $query, Request $request): string
    {
        $search = $request->query('search', '');

        if ($search === '') {
            return '';
        }

        $searchable = Arr::where($this->columns, fn (Column $c) => $c->searchable);

        if (empty($searchable)) {
            return '';
        }

        $query->where(function (Builder $q) use ($search, $searchable): void {
            foreach ($searchable as $column) {
                if ($column->searchUsing) {
                    ($column->searchUsing)($q, $search);
                } else {
                    $q->orWhere(DB::raw('lower('.$column->key.')'), 'like', '%'.mb_strtolower($search).'%');
                }
            }
        });

        return $search;
    }

    protected function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', $this->defaultPerPage);

        if (! in_array($perPage, $this->perPageOptions, true)) {
            return $this->defaultPerPage;
        }

        return $perPage;
    }

    protected function resolveSort(Request $request): string
    {
        $sortParam = $request->query('sort', $this->defaultSort);
        $sortKey = ltrim($sortParam, '-');
        $descending = str_starts_with($sortParam, '-');

        $column = Arr::first($this->columns, fn (Column $c) => $c->key === $sortKey && $c->sortable);

        if (! $column) {
            $descending = str_starts_with($this->defaultSort, '-');

            return $this->defaultSort;
        }

        return $descending ? '-'.$sortKey : $sortKey;
    }

    protected function extractFilterValues(Request $request): array
    {
        $values = [];

        foreach ($this->filters as $filter) {
            $value = $request->query($filter->key);

            if ($value !== null && $value !== '') {
                $values[$filter->key] = $value;
            }
        }

        return $values;
    }

    protected function buildSorts(): array
    {
        $sorts = [];

        foreach ($this->columns as $column) {
            if (! $column->sortable) {
                continue;
            }

            if ($column->sortUsing) {
                $sorts[] = AllowedSort::custom(
                    $column->key,
                    $this->wrapSortCallback($column->sortUsing),
                    $column->key,
                );
            } else {
                $sorts[] = AllowedSort::field($column->key);
            }
        }

        return $sorts;
    }

    protected function wrapSortCallback(callable $callback): Sort
    {
        return new class($callback) implements Sort
        {
            public function __construct(private mixed $callback) {}

            public function __invoke(Builder $query, bool $descending, string $property): void
            {
                ($this->callback)($query, $descending ? 'desc' : 'asc');
            }
        };
    }

    protected function buildFilters(): array
    {
        $filters = [];

        foreach ($this->filters as $filter) {
            if ($filter->queryCallback) {
                $callback = $filter->queryCallback;

                $wrapped = function (Builder $query, array|string $value) use ($callback): void {
                    $values = is_array($value) ? $value : explode(',', $value);

                    if (count($values) === 1) {
                        $callback($query, $values[0]);

                        return;
                    }

                    $query->where(function (Builder $query) use ($values, $callback): void {
                        foreach ($values as $v) {
                            $query->orWhere(function (Builder $query) use ($callback, $v): void {
                                $callback($query, $v);
                            });
                        }
                    });
                };

                $filters[] = AllowedFilter::callback($filter->key, $wrapped);
            } else {
                $filters[] = AllowedFilter::exact($filter->key);
            }
        }

        return $filters;
    }
}
