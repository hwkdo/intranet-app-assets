<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets\Admin;

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Models\SeventhingsMapping as SeventhingsMappingModel;
use Hwkdo\IntranetAppAssets\SeventhingsMappingConfig;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SeventhingsMapping extends Component
{
    public string $local_attribute = '';

    public string $itexia_attribute = '';

    public function addMapping(): void
    {
        $this->validate([
            'local_attribute' => 'required|string|in:'.implode(',', array_keys(SeventhingsMappingConfig::localAttributes())),
            'itexia_attribute' => 'required|string|in:'.implode(',', array_keys(SeventhingsMappingConfig::itexiaAttributes())),
        ]);

        $exists = SeventhingsMappingModel::where('local_attribute', $this->local_attribute)
            ->where('itexia_attribute', $this->itexia_attribute)
            ->exists();

        if ($exists) {
            Flux::toast('Diese Zuordnung existiert bereits.', variant: 'warning');

            return;
        }

        $maxOrder = SeventhingsMappingModel::max('sort_order') ?? 0;
        SeventhingsMappingModel::create([
            'local_attribute' => $this->local_attribute,
            'itexia_attribute' => $this->itexia_attribute,
            'sort_order' => $maxOrder + 1,
        ]);

        Flux::toast('Zuordnung wurde hinzugefügt.', variant: 'success');
        $this->reset('local_attribute', 'itexia_attribute');
        $this->dispatch('mapping-saved');
    }

    public function deleteMapping(int $id): void
    {
        SeventhingsMappingModel::findOrFail($id)->delete();
        Flux::toast('Zuordnung wurde entfernt.', variant: 'success');
        $this->dispatch('mapping-saved');
    }

    #[Computed]
    public function mappings(): \Illuminate\Database\Eloquent\Collection
    {
        return SeventhingsMappingModel::orderBy('sort_order')->orderBy('id')->get();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.admin.seventhings-mapping');
    }
}
