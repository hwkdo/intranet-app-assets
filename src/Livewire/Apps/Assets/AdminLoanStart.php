<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Services\AssetLoanService;
use Hwkdo\IntranetAppAssets\Services\ScheduledReturnReminderService;
use Hwkdo\IntranetAppAssets\Support\AdminHandoverChannel;
use Hwkdo\IntranetAppAssets\Support\AdminLoanEligibility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Asset verleihen')]
class AdminLoanStart extends Component
{
    public Asset $asset;

    public ?string $recipientUserId = null;

    public string $channel = AdminHandoverChannel::Self;

    public string $scheduledDate = '';

    public string $scheduledTime = '';

    #[Validate('nullable|string|max:5000')]
    public string $note = '';

    public function mount(Asset $asset): void
    {
        $this->authorize('manage-app-assets');

        if ($asset->trashed()) {
            abort(404);
        }

        if (! AdminLoanEligibility::isEligible($asset)) {
            session()->flash('error', 'Dieses Asset kann derzeit nicht verliehen werden.');
            $this->redirect(route('apps.assets.liste'), navigate: true);

            return;
        }

        $asset->load(['type', 'vendor', 'owner']);
        $this->asset = $asset;

        $window = app(ScheduledReturnReminderService::class)->allowedLoanScheduleWindow();
        $defaultScheduled = $window['min']->copy();
        $this->scheduledDate = $defaultScheduled->format('Y-m-d');
        $this->scheduledTime = $defaultScheduled->format('H:i');
    }

    public function submit(AssetLoanService $service): void
    {
        $this->authorize('manage-app-assets');

        $this->validate([
            'recipientUserId' => ['required', 'exists:users,id'],
            'channel' => ['required', 'string', Rule::in(AdminHandoverChannel::selectableValues())],
            'scheduledDate' => ['required', 'date'],
            'scheduledTime' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:5000'],
        ], [
            'recipientUserId.required' => 'Bitte einen Empfänger wählen.',
            'channel.in' => 'Bitte einen verfügbaren Bestätigungsweg wählen.',
        ]);

        $recipient = User::query()->aktiv()->whereKey((int) $this->recipientUserId)->first();
        if ($recipient === null) {
            $this->addError('recipientUserId', 'Empfänger muss ein aktiver Mitarbeiter sein.');

            return;
        }

        $loanDueAt = Carbon::parse(
            $this->scheduledDate.' '.$this->scheduledTime,
            config('app.timezone'),
        );

        $validationMessage = app(ScheduledReturnReminderService::class)->validateLoanDueAt($loanDueAt);
        if ($validationMessage !== null) {
            $this->addError('scheduledDate', $validationMessage);

            return;
        }

        try {
            $handover = $service->startLoan(
                $this->asset->fresh() ?? $this->asset,
                (int) auth()->id(),
                (int) $recipient->id,
                $loanDueAt,
                $this->channel,
                $this->note,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('recipientUserId', $e->getMessage());

            return;
        }

        $this->asset->refresh();

        if ($this->channel === AdminHandoverChannel::PasswordNow) {
            $this->redirect(route('apps.assets.admin.handover.confirm-password', $handover), navigate: true);

            return;
        }

        if ($this->channel === AdminHandoverChannel::SignopadZentrale) {
            session()->flash(
                'message',
                'Verleih liegt an der Zentrale bereit. Der Empfänger bestätigt dort per Signopad; die Rückgabe ist bis '
                    .$loanDueAt->format('d.m.Y H:i').' terminiert.',
            );
            $this->redirect(route('apps.assets.zentrale'), navigate: true);

            return;
        }

        session()->flash(
            'message',
            'Verleih wurde angelegt. Nach Bestätigung gilt die Rückgabe bis '.$loanDueAt->format('d.m.Y H:i').'.',
        );
        $this->redirect(route('apps.assets.handover.show', $handover), navigate: true);
    }

    public function minScheduleDate(): string
    {
        return app(ScheduledReturnReminderService::class)
            ->allowedLoanScheduleWindow()['min']
                ->format('Y-m-d');
    }

    public function maxScheduleDate(): string
    {
        return app(ScheduledReturnReminderService::class)
            ->allowedLoanScheduleWindow()['max']
                ->format('Y-m-d');
    }

    public function reminder2Hours(): int
    {
        return IntranetAppAssetsSettings::resolvedAppSettings()->returnReminder2Hours;
    }

    public function loanMaxDays(): int
    {
        return IntranetAppAssetsSettings::resolvedAppSettings()->loanMaxDays;
    }

    /**
     * @return Collection<int, User>
     */
    public function users(): Collection
    {
        return User::query()
            ->aktiv()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();
    }

    public function render(): View
    {
        return view('intranet-app-assets::livewire.apps.assets.admin-loan-start', [
            'users' => $this->users(),
        ]);
    }
}
