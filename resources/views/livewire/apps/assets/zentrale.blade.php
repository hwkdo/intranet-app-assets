<div>
    @if ($embedded)
        @include('intranet-app-assets::livewire.apps.assets.partials.zentrale-content')
    @else
        <x-intranet-app-assets::assets-layout
            heading="Zentrale"
            subheading="Übergaben per Signopad bestätigen"
        >
            @include('intranet-app-assets::livewire.apps.assets.partials.zentrale-content')
        </x-intranet-app-assets::assets-layout>
    @endif
</div>
