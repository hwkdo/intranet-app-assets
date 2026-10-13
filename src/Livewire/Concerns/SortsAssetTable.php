<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Livewire\Concerns;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Support\AssetTableSort;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

trait SortsAssetTable
{
    #[Url(as: 'sort', except: '')]
    public string $sortColumn = '';

    #[Url(as: 'sdir', except: '')]
    public string $sortDirection = '';

    public function sortBy(string $column): void
    {
        if (! in_array($column, $this->sortableColumns(), true)) {
            return;
        }

        if ($this->activeSortColumn() === $column) {
            $this->sortColumn = $column;
            $this->sortDirection = $this->activeSortDirection() === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    public function activeSortColumn(): string
    {
        return in_array($this->sortColumn, $this->sortableColumns(), true)
            ? $this->sortColumn
            : $this->defaultSortColumn();
    }

    public function activeSortDirection(): string
    {
        if ($this->sortColumn === '' || ! in_array($this->sortColumn, $this->sortableColumns(), true)) {
            return $this->defaultSortDirection();
        }

        return $this->sortDirection === 'desc' ? 'desc' : 'asc';
    }

    /**
     * @param  Builder<Asset>  $query
     * @return Builder<Asset>
     */
    protected function applyAssetSort(Builder $query): Builder
    {
        return AssetTableSort::apply($query, $this->activeSortColumn(), $this->activeSortDirection());
    }

    protected function defaultSortColumn(): string
    {
        return 'model';
    }

    protected function defaultSortDirection(): string
    {
        return 'asc';
    }

    /**
     * @return list<string>
     */
    abstract protected function sortableColumns(): array;
}
