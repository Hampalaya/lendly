<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait FiltersRecords
{
    public string $recordSearch = '';

    public string $recordSort = 'default';

    public function updatingRecordSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRecordSort(): void
    {
        $this->resetPage();
    }

    public function toggleRecordSort(): void
    {
        $this->recordSort = $this->effectiveRecordSort() === 'oldest' ? 'newest' : 'oldest';
        $this->resetPage();
    }

    protected function effectiveRecordSort(): string
    {
        return $this->recordSort === 'oldest' ? 'oldest' : 'newest';
    }

    protected function filterRecords(Builder $query, array $columns = [], array $relations = []): Builder
    {
        $search = mb_substr(trim($this->recordSearch), 0, 120);
        if ($search !== '' && ($columns || $relations)) {
            $query->where(function (Builder $match) use ($search, $columns, $relations) {
                foreach ($columns as $column) {
                    $match->orWhere($column, 'like', '%'.$search.'%');
                }
                foreach ($relations as $relation => $fields) {
                    $match->orWhereHas($relation, function (Builder $related) use ($fields, $search) {
                        $related->where(function (Builder $words) use ($fields, $search) {
                            foreach ($fields as $field) {
                                $words->orWhere($field, 'like', '%'.$search.'%');
                            }
                        });
                    });
                }
            });
        }
        // User input never supplies an SQL column or direction.
        if (in_array($this->recordSort, ['newest', 'oldest'], true)) {
            $direction = $this->recordSort === 'oldest' ? 'asc' : 'desc';
            $query->reorder($query->getModel()->qualifyColumn('created_at'), $direction)
                ->orderBy($query->getModel()->qualifyColumn('id'), $direction);
        }

        return $query;
    }
}
