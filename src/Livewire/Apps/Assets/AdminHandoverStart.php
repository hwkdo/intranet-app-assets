<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\AdminAssistedHandoverService;
use Hwkdo\IntranetAppAssets\Support\AdminHandoverChannel;
use Hwkdo\IntranetAppAssets\Support\AdminHandoverEligibility;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Asset übergeben')]
class AdminHandoverStart extends Component
{
    public Asset $asset;

    public ?string $recipientUserId = null;

    public string $channel = AdminHandoverChannel::Self;

    public function mount(Asset $asset): void
    {
        $this->authorize('manage-app-assets');

        if ($asset->trashed()) {
            abort(404);
        }

        if (! AdminHandoverEligibility::isEligible($asset)) {
            session()->flash('error', 'Dieses Asset kann derzeit nicht übergeben werden.');
            $this->redirect(route('apps.assets.liste'), navigate: true);

            return;
        }

        $asset->load(['type', 'vendor', 'owner']);
        $this->asset = $asset;

        if ($asset->user_id !== null) {
            $this->recipientUserId = (string) $asset->user_id;
        }
    }

    public function submit(AdminAssistedHandoverService $service): void
    {
        $this->authorize('manage-app-assets');

        $needsRecipient = $this->asset->user_id === null;

        $rules = [
            'channel' => ['required', 'string', Rule::in(AdminHandoverChannel::selectableValues())],
        ];
        if ($needsRecipient) {
            $rules['recipientUserId'] = ['required', 'exists:users,id'];
        }

        $this->validate($rules, [
            'recipientUserId.required' => 'Bitte einen Empfänger wählen.',
            'channel.in' => 'Bitte einen verfügbaren Bestätigungsweg wählen.',
        ]);

        try {
            $handover = $service->prepareOpenHandover(
                $this->asset->fresh() ?? $this->asset,
                (int) auth()->id(),
                $needsRecipient ? (int) $this->recipientUserId : null,
            );
            $service->setPendingConfirmationChannel(
                $handover,
                $this->channel,
                (int) auth()->id(),
            );
            $handover->refresh();
        } catch (\InvalidArgumentException $e) {
            $this->addError('recipientUserId', $e->getMessage());

            return;
        }

        $this->asset->refresh();
        $this->recordChannelChoice($handover);

        if ($this->channel === AdminHandoverChannel::PasswordNow) {
            $this->redirect(route('apps.assets.admin.handover.confirm-password', $handover), navigate: true);

            return;
        }

        if ($this->channel === AdminHandoverChannel::SignopadZentrale) {
            session()->flash(
                'message',
                'Übergabe liegt an der Zentrale bereit. Der Empfänger kann dort per Signopad bestätigen.',
            );
            $this->redirect(route('apps.assets.zentrale'), navigate: true);

            return;
        }

        session()->flash(
            'message',
            'Übergabe wurde angelegt. Der Empfänger kann sie jetzt selbst bestätigen (Meine Assets / offene Übergaben).',
        );
        $this->redirect(route('apps.assets.handover.show', $handover), navigate: true);
    }

    #[Computed]
    public function users(): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();
    }

    #[Computed]
    public function openHandover(): ?Handover
    {
        if ($this->asset->user_id === null) {
            return null;
        }

        return Handover::query()
            ->where('asset_id', $this->asset->id)
            ->where('recipient_user_id', $this->asset->user_id)
            ->open()
            ->orderByDesc('id')
            ->first();
    }

    public function render(): View
    {
        return view('intranet-app-assets::livewire.apps.assets.admin-handover-start');
    }

    private function recordChannelChoice(Handover $handover): void
    {
        $asset = $handover->asset ?? $this->asset;
        $asset->historyEntries()->create([
            'event' => AssetHistory::EventAdminHandoverStarted,
            'user_id' => auth()->id(),
            'reason' => 'Admin hat Übergabe gestartet (Kanal: '.$this->channelLabel().').',
            'meta' => [
                'source' => 'assets.admin.handover.start',
                'handover_id' => $handover->id,
                'channel' => $this->channel,
                'recipient_user_id' => $handover->recipient_user_id,
            ],
        ]);
    }

    private function channelLabel(): string
    {
        return match ($this->channel) {
            AdminHandoverChannel::PasswordNow => 'Passwort vor Ort',
            AdminHandoverChannel::SignopadZentrale => 'Signopad Zentrale',
            default => 'Empfänger bestätigt selbst',
        };
    }
}
