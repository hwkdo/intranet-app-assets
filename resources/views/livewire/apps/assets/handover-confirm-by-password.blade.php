<?php

use Hwkdo\IntranetAppAssets\Models\Handover;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public function mount(Handover $handover): void
    {
        if ($handover->recipient_user_id !== auth()->id()) {
            abort(403);
        }
        if ($handover->isConfirmed()) {
            session()->flash('message', 'Diese Übergabe wurde bereits bestätigt.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);
            return;
        }

        $handover->update([
            'confirmed_at' => now(),
            'confirmation_method' => 'password',
        ]);
        session()->flash('message', 'Übergabe wurde per Passwort bestätigt.');
        $this->redirect(route('apps.assets.meine-assets'), navigate: true);
    }
}; ?>
<div>
    <div class="flex items-center justify-center py-12">
        <flux:spinner size="lg" />
    </div>
</div>
