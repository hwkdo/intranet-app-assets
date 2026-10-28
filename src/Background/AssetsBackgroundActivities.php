<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Background;

use Hwkdo\IntranetAppAssets\Commands\InvoiceMailsStaleCheckCommand;
use Hwkdo\IntranetAppAssets\IntranetAppAssets;
use Hwkdo\IntranetAppAssets\Jobs\AnalyzeD3InvoiceJob;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\AnalyzeInvoiceMailAttachmentJob;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\ApplyInvoiceMailMailboxActionJob;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\ClassifyInvoiceMailJob;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\ImportInvoiceMailsJob;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\IngestInvoiceMailJob;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\UploadInvoiceMailToD3Job;
use Hwkdo\IntranetAppAssets\Jobs\NotifyAssetItexiaInventoryJob;
use Hwkdo\IntranetAppAssets\Mail\AssetCreatedWithItexiaMail;
use Hwkdo\IntranetAppAssets\Mail\AssetDeletedInItexiaInventoryMail;
use Hwkdo\IntranetAppAssets\Mail\AssetItexiaInvoiceAddedMail;
use Hwkdo\IntranetAppAssets\Mail\AssetItexiaLocationChangedMail;
use Hwkdo\IntranetAppAssets\Mail\AssetItexiaUuidLinkedMail;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityFrequency;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityParameter;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;

final class AssetsBackgroundActivities
{
    /**
     * @return list<BackgroundActivityDefinition>
     */
    public static function definitions(): array
    {
        $id = IntranetAppAssets::identifier();
        $name = IntranetAppAssets::app_name();
        $all = BackgroundActivityCadence::cases();
        $production = ['production'];

        return [
            BackgroundActivityDefinition::scheduled(
                key: 'assets.invoice-auto-resolve',
                label: 'Rechnungen nachziehen',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-assets:invoice-auto-resolve',
                frequency: BackgroundActivityFrequency::dailyAt('03:32'),
                description: 'Sucht fehlende Rechnungsnummern in D3.',
                allowedCadences: $all,
                environments: $production,
                sort: 10,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'assets.d3-invoice-analyses-backfill',
                label: 'Rechnungsanalysen nachziehen',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-assets:d3-invoice-analyses-backfill',
                frequency: BackgroundActivityFrequency::weeklyOn(6, '04:12'),
                description: 'Stellt D3-Rechnungsanalysen in die Queue.',
                allowedCadences: $all,
                withoutOverlappingMinutes: 120,
                runInBackground: true,
                environments: $production,
                sort: 20,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'assets.domain-check',
                label: 'Domain-Check',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-assets:domain-check',
                frequency: BackgroundActivityFrequency::dailyAt('03:02'),
                description: 'Aktualisiert Domain- und LDAP-Last-Seen.',
                allowedCadences: $all,
                environments: $production,
                sort: 30,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'assets.intune-sync',
                label: 'Intune-Sync',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-assets:intune-sync',
                frequency: BackgroundActivityFrequency::dailyAt('03:12'),
                description: 'Synchronisiert Intune-Geräte.',
                allowedCadences: $all,
                environments: $production,
                sort: 40,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'assets.configmgr-sync',
                label: 'ConfigMgr-Sync',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-assets:sync-configmgr-data',
                frequency: BackgroundActivityFrequency::dailyAt('03:22'),
                description: 'Synchronisiert ConfigMgr/SCCM-Daten.',
                allowedCadences: $all,
                environments: $production,
                sort: 50,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'assets.itexia-uuid-sync',
                label: 'Itexia-UUID-Sync',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-assets:itexia-uuid-sync --limit=120',
                frequency: BackgroundActivityFrequency::everyMinutes(15),
                description: 'Synchronisiert Itexia-UUIDs.',
                allowedCadences: $all,
                minEvery: 5,
                maxEvery: 60,
                withoutOverlappingMinutes: 30,
                runInBackground: true,
                environments: $production,
                sort: 60,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'assets.itexia-rooms-sync',
                label: 'Itexia-Raum-Sync',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-assets:itexia-rooms-sync --limit=250',
                frequency: BackgroundActivityFrequency::everyMinutes(15, 10),
                description: 'Synchronisiert Itexia-Raum-IDs.',
                allowedCadences: $all,
                minEvery: 5,
                maxEvery: 60,
                allowsOffset: true,
                withoutOverlappingMinutes: 30,
                runInBackground: true,
                environments: $production,
                sort: 70,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'assets.itexia-image-sync',
                label: 'Itexia-Bild-Sync',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-assets:itexia-image-sync --limit=60',
                frequency: BackgroundActivityFrequency::everyMinutes(15, 7),
                description: 'Synchronisiert Itexia-Bilder.',
                allowedCadences: $all,
                minEvery: 5,
                maxEvery: 60,
                allowsOffset: true,
                withoutOverlappingMinutes: 30,
                runInBackground: true,
                environments: $production,
                sort: 80,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'assets.return-reminders',
                label: 'Rückgabe-Erinnerungen',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-assets:return-reminders',
                frequency: BackgroundActivityFrequency::everyMinutes(15),
                description: 'Versendet Erinnerungen für geplante Asset-Rückgaben.',
                allowedCadences: $all,
                minEvery: 5,
                maxEvery: 60,
                withoutOverlappingMinutes: 15,
                runInBackground: true,
                environments: $production,
                sort: 90,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'assets.owner-assignments',
                label: 'Geplante Besitzerwechsel',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-assets:process-scheduled-owner-assignments',
                frequency: BackgroundActivityFrequency::everyMinutes(15),
                description: 'Führt fällige Besitzerwechsel aus.',
                allowedCadences: $all,
                minEvery: 5,
                maxEvery: 60,
                withoutOverlappingMinutes: 15,
                runInBackground: true,
                environments: $production,
                sort: 100,
            ),
            BackgroundActivityDefinition::scheduled(
                key: InvoiceMailsStaleCheckCommand::ActivityKey,
                label: 'Rechnungs-Mails: hängende Verarbeitung',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-assets:invoice-mails-stale-check',
                frequency: BackgroundActivityFrequency::everyMinutes(15, 4),
                description: 'Stößt hängende Rechnungs-Mails neu an und markiert sie nach mehreren Versuchen als fehlgeschlagen.',
                allowedCadences: $all,
                minEvery: 5,
                maxEvery: 60,
                allowsOffset: true,
                withoutOverlappingMinutes: 15,
                environments: $production,
                parameters: [
                    new BackgroundActivityParameter(
                        key: 'after_minutes',
                        label: 'Hängt nach Minuten',
                        type: 'int',
                        default: 30,
                        min: 5,
                        max: 1440,
                    ),
                    new BackgroundActivityParameter(
                        key: 'max_retries',
                        label: 'Neustarts bis „Fehlgeschlagen“',
                        type: 'int',
                        default: 2,
                        min: 0,
                        max: 10,
                    ),
                ],
                sort: 105,
            ),
            BackgroundActivityDefinition::queued(key: 'assets.invoice-mail-ingest', label: 'Rechnungs-Mail übernehmen', appIdentifier: $id, appName: $name, job: IngestInvoiceMailJob::class, description: 'Holt eine neue Mail aus dem Rechnungs-Postfach.', sort: 130),
            BackgroundActivityDefinition::queued(key: 'assets.invoice-mail-classify', label: 'Rechnungs-Mail klassifizieren', appIdentifier: $id, appName: $name, job: ClassifyInvoiceMailJob::class, description: 'Prüft per JEV, ob die Mail ein Beleg ist.', sort: 131),
            BackgroundActivityDefinition::queued(key: 'assets.invoice-mail-analyze', label: 'Rechnungs-Mail: Anhang analysieren', appIdentifier: $id, appName: $name, job: AnalyzeInvoiceMailAttachmentJob::class, description: 'Ermittelt Empfänger und Belegdaten eines Anhangs.', sort: 132),
            BackgroundActivityDefinition::queued(key: 'assets.invoice-mail-d3-upload', label: 'Rechnungs-Mail: D3-Upload', appIdentifier: $id, appName: $name, job: UploadInvoiceMailToD3Job::class, description: 'Lädt einen Anhang als Zahlungsbeleg nach D3.', sort: 133),
            BackgroundActivityDefinition::queued(key: 'assets.invoice-mail-mailbox-action', label: 'Rechnungs-Mail: Postfach-Aktion', appIdentifier: $id, appName: $name, job: ApplyInvoiceMailMailboxActionJob::class, description: 'Markiert die Mail als gelesen oder verschiebt sie.', sort: 134),
            BackgroundActivityDefinition::queued(key: 'assets.invoice-mail-import', label: 'Rechnungs-Mails importieren', appIdentifier: $id, appName: $name, job: ImportInvoiceMailsJob::class, description: 'Einmaliger Import eines Datumsbereichs.', sort: 135),
            BackgroundActivityDefinition::queued(key: 'assets.d3-invoice-analysis', label: 'D3-Rechnungsanalyse', appIdentifier: $id, appName: $name, job: AnalyzeD3InvoiceJob::class, description: 'Wertet eine D3-Rechnung per KI aus.', sort: 110),
            BackgroundActivityDefinition::queued(key: 'assets.itexia-inventory-notify', label: 'Itexia-Bestand melden', appIdentifier: $id, appName: $name, job: NotifyAssetItexiaInventoryJob::class, description: 'Meldet Bestandsänderungen an Itexia.', sort: 120),
            BackgroundActivityDefinition::mail(key: 'assets.mail-created', label: 'Mail: Asset angelegt', appIdentifier: $id, appName: $name, mailable: AssetCreatedWithItexiaMail::class, sort: 200),
            BackgroundActivityDefinition::mail(key: 'assets.mail-deleted', label: 'Mail: Asset in Itexia gelöscht', appIdentifier: $id, appName: $name, mailable: AssetDeletedInItexiaInventoryMail::class, sort: 210),
            BackgroundActivityDefinition::mail(key: 'assets.mail-invoice', label: 'Mail: Rechnung ergänzt', appIdentifier: $id, appName: $name, mailable: AssetItexiaInvoiceAddedMail::class, sort: 220),
            BackgroundActivityDefinition::mail(key: 'assets.mail-location', label: 'Mail: Standort geändert', appIdentifier: $id, appName: $name, mailable: AssetItexiaLocationChangedMail::class, sort: 230),
            BackgroundActivityDefinition::mail(key: 'assets.mail-uuid', label: 'Mail: UUID verknüpft', appIdentifier: $id, appName: $name, mailable: AssetItexiaUuidLinkedMail::class, sort: 240),
        ];
    }
}
