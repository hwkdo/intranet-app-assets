<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Enums\ReturnScheduleType;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Services\ScheduledReturnReminderService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Rückgabe einleiten')]
class HandoverReturnInitiate extends Component
{
    public Handover $handover;

    public string $scheduleType = ReturnScheduleType::Immediate->value;

    public string $scheduledDate = '';

    public string $scheduledTime = '';

    #[Validate('nullable|string|max:5000')]
    public string $note = '';

    public bool $initiatedByAdmin = false;

    public function mount(Handover $handover): void
    {
        $this->handover = $handover->load(['asset.type', 'asset.vendor', 'recipient', 'issuer']);
        $isAdmin = auth()->user()?->can('manage-app-assets') ?? false;
        $this->initiatedByAdmin = $isAdmin;

        if (! $isAdmin && $handover->recipient_user_id !== auth()->id()) {
            abort(403);
        }

        if (! $handover->isConfirmed() || $handover->isRejected()) {
            session()->flash('message', 'Rückgabe ist nur bei bestätigter Übergabe möglich.');
            $this->redirect(route('apps.assets.handover.show', $handover), navigate: true);
        }

        $asset = $handover->asset;
        if ($asset === null || (! $isAdmin && (int) $asset->user_id !== (int) auth()->id())) {
            session()->flash('message', 'Sie sind laut System nicht der aktuelle Besitzer dieses Assets.');
            $this->redirect(route('apps.assets.handover.show', $handover), navigate: true);
        }

        if ($handover->assetReturns()->whereNull('completed_at')->exists()) {
            session()->flash('message', 'Für diese Übergabe wurde bereits eine Rückgabe eingeleitet.');
            $this->redirect(route('apps.assets.handover.show', $handover), navigate: true);
        }

        if ($handover->assetReturns()->whereNotNull('completed_at')->exists()) {
            session()->flash('message', 'Diese Übergabe ist bereits zurückgegeben worden.');
            $this->redirect(route('apps.assets.handover.show', $handover), navigate: true);
        }

        $window = app(ScheduledReturnReminderService::class)->allowedScheduleWindow();
        $defaultScheduled = $window['min']->copy();
        $this->scheduledDate = $defaultScheduled->format('Y-m-d');
        $this->scheduledTime = $defaultScheduled->format('H:i');
    }

    public function submit(): void
    {
        $this->validate([
            'note' => ['nullable', 'string', 'max:5000'],
            'scheduleType' => ['required', 'in:'.ReturnScheduleType::Immediate->value.','.ReturnScheduleType::Scheduled->value],
        ]);

        $userId = auth()->id();
        $noteText = trim($this->note);
        $scheduleType = ReturnScheduleType::from($this->scheduleType);
        $scheduledAt = null;

        if ($scheduleType === ReturnScheduleType::Scheduled) {
            $this->validate([
                'scheduledDate' => ['required', 'date'],
                'scheduledTime' => ['required', 'date_format:H:i'],
            ]);

            $scheduledAt = Carbon::parse(
                $this->scheduledDate.' '.$this->scheduledTime,
                config('app.timezone'),
            );

            $validationMessage = app(ScheduledReturnReminderService::class)->validateScheduledAt($scheduledAt);
            if ($validationMessage !== null) {
                $this->addError('scheduledDate', $validationMessage);

                return;
            }
        }

        $return = AssetReturn::create([
            'handover_id' => $this->handover->id,
            'initiated_by_user_id' => $userId,
            'schedule_type' => $scheduleType,
            'scheduled_at' => $scheduledAt,
        ]);

        if ($noteText !== '') {
            $return->notes()->create([
                'note' => 'Rückgabe eingeleitet:'."\n\n".$noteText,
                'user_id' => $userId,
            ]);
        }

        $asset = $this->handover->asset;
        if ($asset !== null) {
            $reason = $noteText !== '' ? $noteText : 'Rückgabe eingeleitet.';
            if ($scheduleType === ReturnScheduleType::Scheduled && $scheduledAt !== null) {
                $reason = 'Geplante Rückgabe für '.$scheduledAt->format('d.m.Y H:i').'.'.($noteText !== '' ? ' '.$noteText : '');
            }

            $asset->historyEntries()->create([
                'event' => AssetHistory::EventReturnInitiatedByHolder,
                'user_id' => $userId,
                'reason' => $reason,
                'meta' => [
                    'asset_return_id' => $return->id,
                    'handover_id' => $this->handover->id,
                    'initiated_by_admin' => $this->initiatedByAdmin,
                    'schedule_type' => $scheduleType->value,
                    'scheduled_at' => $scheduledAt?->toIso8601String(),
                ],
            ]);
        }

        $flashMessage = $scheduleType === ReturnScheduleType::Scheduled
            ? 'Die geplante Rückgabe wurde eingeleitet. Sie erhalten Erinnerungen vor dem Termin.'
            : 'Die Rückgabe wurde eingeleitet. Die IT bearbeitet den Vorgang und bestätigt den physischen Empfang.';

        session()->flash('message', $flashMessage);
        $this->redirect(route('apps.assets.handover.show', $this->handover), navigate: true);
    }

    public function minScheduleDate(): string
    {
        return app(ScheduledReturnReminderService::class)
            ->allowedScheduleWindow()
            ['min']
            ->format('Y-m-d');
    }

    public function maxScheduleDate(): string
    {
        return app(ScheduledReturnReminderService::class)
            ->allowedScheduleWindow()
            ['max']
            ->format('Y-m-d');
    }

    public function reminder2Hours(): int
    {
        $settings = IntranetAppAssetsSettings::resolvedAppSettings();

        return $settings->returnReminder2Hours;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.handover-return-initiate');
    }
}
