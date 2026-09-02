<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Notifications;

use Hwkdo\IntranetAppAssets\Enums\ReturnReminderPhase;
use Hwkdo\IntranetAppAssets\IntranetAppAssets;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Support\AssetReturnSchedulePresenter;
use Hwkdo\IntranetAppBase\Notifications\IntranetNotification;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class ReturnReminderNotification extends IntranetNotification
{
    public function __construct(
        public readonly AssetReturn $return,
        public readonly ReturnReminderPhase $phase,
    ) {
        parent::__construct();
    }

    public function typeKey(): string
    {
        return 'assets.return_reminder';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $assetName = $this->return->handover?->asset?->display_name ?? 'Asset';
        $scheduledAt = AssetReturnSchedulePresenter::formattedScheduledAt($this->return->scheduled_at) ?? '—';

        if ($this->phase->isOverdue()) {
            return (new MailMessage)
                ->subject('Dringend: Geplante Rückgabe überfällig – '.$assetName)
                ->line('Die geplante Rückgabe für '.$assetName.' war für '.$scheduledAt.' vorgesehen und ist noch nicht abgeschlossen.')
                ->line('Bitte geben Sie das Gerät umgehend zurück oder wenden Sie sich an die IT.')
                ->action('Rückgabe anzeigen', $this->actionUrl());
        }

        $hoursLabel = (string) match ($this->phase) {
            ReturnReminderPhase::Upcoming1 => IntranetAppAssetsSettings::resolvedAppSettings()->returnReminder1Hours,
            ReturnReminderPhase::Upcoming2 => IntranetAppAssetsSettings::resolvedAppSettings()->returnReminder2Hours,
            ReturnReminderPhase::Overdue => IntranetAppAssetsSettings::resolvedAppSettings()->returnReminder3Hours,
        };

        return (new MailMessage)
            ->subject('Erinnerung: Geplante Rückgabe – '.$assetName)
            ->line('Erinnerung an Ihre geplante Rückgabe für '.$assetName.' am '.$scheduledAt.'.')
            ->line('Die Rückgabe steht in Kürze an (ca. '.$hoursLabel.' Stunden vor dem Termin).')
            ->action('Rückgabe anzeigen', $this->actionUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $assetName = $this->return->handover?->asset?->display_name ?? 'Asset';
        $scheduledAt = AssetReturnSchedulePresenter::formattedScheduledAt($this->return->scheduled_at) ?? '—';

        if ($this->phase->isOverdue()) {
            return $this->inboxPayload(
                title: 'Rückgabe überfällig',
                body: $assetName.' · Termin '.$scheduledAt.' – bitte umgehend zurückgeben.',
                url: $this->actionUrl(),
                appIdentifier: IntranetAppAssets::identifier(),
            );
        }

        return $this->inboxPayload(
            title: 'Geplante Rückgabe',
            body: $assetName.' · Termin '.$scheduledAt,
            url: $this->actionUrl(),
            appIdentifier: IntranetAppAssets::identifier(),
        );
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        $assetName = $this->return->handover?->asset?->display_name ?? 'Asset';

        if ($this->phase->isOverdue()) {
            return (new WebPushMessage)
                ->title('Rückgabe überfällig')
                ->body($assetName.' – bitte umgehend zurückgeben.')
                ->data(['url' => $this->actionUrl()]);
        }

        return (new WebPushMessage)
            ->title('Geplante Rückgabe')
            ->body($assetName.' – Rückgabe-Termin steht an.')
            ->data(['url' => $this->actionUrl()]);
    }

    /**
     * @return array{preview: string, topic: string, url: string}
     */
    public function toTeams(object $notifiable): array
    {
        $assetName = $this->return->handover?->asset?->display_name ?? 'Asset';
        $scheduledAt = AssetReturnSchedulePresenter::formattedScheduledAt($this->return->scheduled_at) ?? '—';

        if ($this->phase->isOverdue()) {
            return [
                'preview' => 'Dringend: Rückgabe überfällig – '.$assetName.' (Termin '.$scheduledAt.')',
                'topic' => 'Assets',
                'url' => $this->actionUrl(),
            ];
        }

        return [
            'preview' => 'Erinnerung: Geplante Rückgabe '.$assetName.' am '.$scheduledAt,
            'topic' => 'Assets',
            'url' => $this->actionUrl(),
        ];
    }

    private function actionUrl(): string
    {
        $handover = $this->return->handover;

        if ($handover !== null) {
            return route('apps.assets.handover.show', $handover);
        }

        return route('apps.assets.meine-assets');
    }
}
