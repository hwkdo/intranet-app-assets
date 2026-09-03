<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\AdminAssistedHandoverService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Assets – Zentrale')]
class Zentrale extends Component
{
    public bool $embedded = false;

    public bool $showConfirmModal = false;

    public ?int $selectedHandoverId = null;

    public ?string $signatureData = null;

    public function mount(): void
    {
        $this->authorize('see-app-assets-zentrale');
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            'echo-private:assets-zentrale-channel,.handover-queue-changed' => 'refreshQueueFromBroadcast',
        ];
    }

    public function refreshQueueFromBroadcast(?array $event = null): void
    {
        unset($this->pendingHandovers);

        if ($this->selectedHandoverId === null) {
            return;
        }

        $stillPending = Handover::query()
            ->pendingSignopadZentrale()
            ->whereKey($this->selectedHandoverId)
            ->exists();

        if (! $stillPending) {
            $this->closeConfirm();
        }
    }

    #[Computed]
    public function pendingHandovers(): Collection
    {
        return Handover::query()
            ->pendingSignopadZentrale()
            ->with(['asset.type', 'asset.vendor', 'recipient', 'issuer'])
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function selectedHandover(): ?Handover
    {
        if ($this->selectedHandoverId === null) {
            return null;
        }

        return Handover::query()
            ->with(['asset.type', 'asset.vendor', 'recipient', 'issuer'])
            ->find($this->selectedHandoverId);
    }

    public function openConfirm(int $handoverId): void
    {
        $this->authorize('see-app-assets-zentrale');

        $handover = Handover::query()->pendingSignopadZentrale()->findOrFail($handoverId);

        $this->selectedHandoverId = $handover->id;
        $this->signatureData = null;
        $this->showConfirmModal = true;
        unset($this->selectedHandover);
    }

    public function closeConfirm(): void
    {
        $this->showConfirmModal = false;
        $this->selectedHandoverId = null;
        $this->signatureData = null;
        unset($this->selectedHandover);
    }

    #[On('signature-confirmed')]
    public function onSignatureConfirmed(string $img_src, string $base64, array $checkboxes): void
    {
        if (! $this->showConfirmModal) {
            return;
        }

        $this->signatureData = $base64;
    }

    public function confirmHandover(AdminAssistedHandoverService $service): void
    {
        $this->authorize('see-app-assets-zentrale');

        $this->validate(
            [
                'signatureData' => ['required', 'string'],
                'selectedHandoverId' => ['required', 'integer'],
            ],
            [
                'signatureData.required' => 'Bitte zuerst die Unterschrift am Signopad erfassen.',
            ],
        );

        $handover = Handover::query()->pendingSignopadZentrale()->findOrFail((int) $this->selectedHandoverId);

        try {
            $service->confirmAtZentraleWithSignopad(
                $handover,
                (int) auth()->id(),
                (string) $this->signatureData,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('signatureData', $e->getMessage());

            return;
        }

        $this->closeConfirm();
        unset($this->pendingHandovers);

        session()->flash('message', 'Übergabe wurde an der Zentrale per Signopad bestätigt.');
    }

    public function render(): View
    {
        return view('intranet-app-assets::livewire.apps.assets.zentrale');
    }
}
