<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Assets')] class extends Component {
};
?>
<div>
<x-intranet-app-assets::assets-layout heading="Assets" subheading="Asset-Verwaltung">
</x-intranet-app-assets::assets-layout>
</div>