<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\AdminAssistedHandoverService;
use Hwkdo\IntranetAppAssets\Services\RecipientHandoverConfirmationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Übergabe per Passwort bestätigen')]
class AdminHandoverConfirmPassword extends Component
{
    public Handover $handover;

    public string $password = '';

    public function mount(Handover $handover): void
    {
        $this->authorize('manage-app-assets');

        $handover->load(['asset.type', 'asset.vendor', 'recipient', 'issuer']);

        if ($handover->isConfirmed() || $handover->isRejected() || $handover->isSuperseded()) {
            session()->flash('error', 'Diese Übergabe kann nicht mehr bestätigt werden.');
            $this->redirect(route('apps.assets.liste'), navigate: true);

            return;
        }

        if ($handover->recipient_user_id === null) {
            session()->flash('error', 'Übergabe ohne Empfänger.');
            $this->redirect(route('apps.assets.liste'), navigate: true);

            return;
        }

        if ((int) $handover->recipient_user_id === (int) auth()->id()) {
            session()->flash('message', 'Bitte die Übergabe über den Empfänger-Weg bestätigen.');
            $this->redirect(route('apps.assets.handover.confirm', $handover), navigate: true);

            return;
        }

        $this->handover = $handover;
    }

    public function submit(AdminAssistedHandoverService $service): void
    {
        $this->authorize('manage-app-assets');

        $this->validate([
            'password' => ['required', 'string'],
        ]);

        $rateKey = 'admin-handover-password:'.auth()->id().':'.$this->handover->id;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            throw ValidationException::withMessages([
                'password' => "Zu viele Fehlversuche. Bitte in {$seconds} Sekunden erneut versuchen.",
            ]);
        }

        try {
            $service->confirmWithRecipientPassword(
                $this->handover->fresh() ?? $this->handover,
                (int) auth()->id(),
                $this->password,
            );
        } catch (ValidationException $e) {
            RateLimiter::hit($rateKey, 120);
            $this->password = '';
            throw $e;
        } catch (\InvalidArgumentException $e) {
            RateLimiter::hit($rateKey, 120);
            $this->password = '';
            $this->addError('password', $e->getMessage());

            return;
        }

        RateLimiter::clear($rateKey);
        $this->password = '';

        session()->flash('message', 'Übergabe wurde vor Ort per Empfänger-Passwort bestätigt.');
        $this->redirect(route('apps.assets.show', [$this->handover->asset_id, 'from' => 'liste']), navigate: true);
    }

    public function confirmWithoutPasswordForLocalDev(RecipientHandoverConfirmationService $confirmationService): void
    {
        if (! app()->isLocal()) {
            abort(403);
        }

        $user = auth()->user();
        if ($user === null || ! $user->hasRole('Super Admin')) {
            abort(403);
        }

        $this->authorize('manage-app-assets');

        $handover = $this->handover->fresh() ?? $this->handover;
        $adminId = (int) auth()->id();
        $recipientId = (int) $handover->recipient_user_id;

        $confirmationService->confirmForRecipient(
            $handover,
            $recipientId,
            RecipientHandoverConfirmationService::METHOD_PASSWORD,
            null,
            $adminId,
        );

        $asset = $handover->asset;
        if ($asset !== null) {
            $asset->historyEntries()->create([
                'event' => AssetHistory::EventHandoverConfirmedAssistedByAdmin,
                'user_id' => $adminId,
                'reason' => 'Übergabe vor Ort per Empfänger-Passwort bestätigt (Admin-Assistent, Dev-Bypass).',
                'meta' => [
                    'handover_id' => $handover->id,
                    'recipient_user_id' => $recipientId,
                    'assisted_by_admin_id' => $adminId,
                    'dev_bypass' => true,
                ],
            ]);
        }

        session()->flash('message', 'Übergabe wurde (Dev) ohne Passwort bestätigt.');
        $this->redirect(route('apps.assets.show', [$handover->asset_id, 'from' => 'liste']), navigate: true);
    }

    public function render(): View
    {
        return view('intranet-app-assets::livewire.apps.assets.admin-handover-confirm-password');
    }
}
