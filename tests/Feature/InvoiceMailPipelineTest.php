<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonInterface;
use Hwkdo\D3RestLaravel\Facades\D3RestLaravel;
use Hwkdo\IntranetAppAssets\Contracts\InvoiceMailboxClientInterface;
use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailAttachmentStatus;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailD3Status;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailMailboxAction;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailMatchStatus;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailSource;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailStatus;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\AnalyzeInvoiceMailAttachmentJob;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\ClassifyInvoiceMailJob;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\IngestInvoiceMailJob;
use Illuminate\Bus\UniqueLock;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Models\InvoiceMail;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailAttachment;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailAttachmentAnalyzer;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailClassifier;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailD3UploadService;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailDocumentReader;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailIngestService;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailMailboxActionService;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailRecipientMatcher;
use Hwkdo\IntranetAppAssets\Support\InvoiceMailAccompanyingDocument;
use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use Hwkdo\IntranetAppBase\Data\AiClassificationResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

final class FakeInvoiceMailbox implements InvoiceMailboxClientInterface
{
    /** @var list<array{0: string, 1: string}> */
    public array $moved = [];

    /** @var list<string> */
    public array $read = [];

    public function getMessage(string $upn, string $messageId): array
    {
        return [
            'id' => $messageId,
            'internet_message_id' => '<'.$messageId.'@lieferant.de>',
            'from_name' => 'Lieferant GmbH',
            'from_address' => 'rechnung@lieferant.de',
            'to' => ['rechnungen@hwk-do.de'],
            'subject' => 'Ihre Rechnung 4711',
            'received_at' => '2026-10-27T08:00:00+00:00',
            'body' => '<p>Sehr geehrte Frau Müller,<br>anbei die Rechnung.</p>',
            'body_type' => 'html',
            'has_attachments' => true,
        ];
    }

    public function listAttachments(string $upn, string $messageId): array
    {
        return [
            ['id' => 'att-1', 'name' => 'Rechnung 4711.pdf', 'content_type' => 'application/pdf', 'size' => 1234, 'is_inline' => false, 'content' => '%PDF-1.4 test'],
            ['id' => 'att-2', 'name' => 'logo.png', 'content_type' => 'image/png', 'size' => 900, 'is_inline' => true, 'content' => 'png'],
        ];
    }

    public function messageIdsReceivedBetween(string $upn, CarbonInterface $from, CarbonInterface $to, string $folderId = 'inbox'): iterable
    {
        return [];
    }

    public function folders(string $upn): array
    {
        return [['id' => 'folder-1', 'name' => 'Erledigt']];
    }

    public function markAsRead(string $upn, string $messageId): void
    {
        $this->read[] = $messageId;
    }

    public function move(string $upn, string $messageId, string $folderId): string
    {
        $this->moved[] = [$messageId, $folderId];

        return 'moved-'.$messageId;
    }
}

function invoiceMailSettings(array $overrides = []): void
{
    IntranetAppAssetsSettings::persistAppSettings(
        AppSettings::from(array_merge((new AppSettings)->toArray(), $overrides)),
    );
}

function rememberLdapDisplayName(User $user, ?string $displayName): void
{
    Cache::flexible(
        'users:ldap_displayname:'.trim((string) $user->username),
        [300, 3600],
        fn (): ?string => $displayName,
    );
}

function makeInvoiceMail(array $attributes = []): InvoiceMail
{
    return InvoiceMail::query()->create(array_merge([
        'graph_message_id' => 'msg-'.uniqid('', true),
        'mailbox_upn' => 'rechnungen@hwk-do.de',
        'source' => InvoiceMailSource::Webhook,
        'from_name' => 'Lieferant GmbH',
        'from_address' => 'rechnung@lieferant.de',
        'subject' => 'Rechnung 4711',
        'received_at' => now(),
        'body' => 'Anbei die Rechnung.',
        'body_type' => 'text',
        'status' => InvoiceMailStatus::Received,
        'report_mode' => true,
    ], $attributes));
}

function makeInvoiceAttachment(InvoiceMail $mail, array $attributes = []): InvoiceMailAttachment
{
    return $mail->attachments()->create(array_merge([
        'filename' => 'rechnung.pdf',
        'mime_type' => 'application/pdf',
        'size' => 50_000,
        'is_inline' => false,
        'path' => $mail->storageDirectory().'/01-rechnung.pdf',
        'sha256' => hash('sha256', uniqid('', true)),
        'status' => InvoiceMailAttachmentStatus::Pending,
        'd3_status' => InvoiceMailD3Status::None,
    ], $attributes));
}

beforeEach(function (): void {
    Permission::findOrCreate('see-app-assets', 'web');
    Permission::findOrCreate('manage-app-assets', 'web');
});

it('übernimmt eine mail mit anhängen lokal und erkennt dubletten', function (): void {
    Storage::fake('local');
    app()->instance(InvoiceMailboxClientInterface::class, new FakeInvoiceMailbox);

    $service = app(InvoiceMailIngestService::class);
    $mail = $service->ingest('rechnungen@hwk-do.de', 'msg-1', InvoiceMailSource::Webhook);

    expect($mail)->not->toBeNull()
        ->and($mail->subject)->toBe('Ihre Rechnung 4711')
        ->and($mail->report_mode)->toBeTrue()
        ->and($mail->attachments()->count())->toBe(2);

    $pdf = $mail->attachments()->where('mime_type', 'application/pdf')->first();
    Storage::disk('local')->assertExists($pdf->path);
    expect($pdf->sha256)->toBe(hash('sha256', '%PDF-1.4 test'))
        ->and($pdf->isCandidate())->toBeTrue()
        ->and($mail->attachments()->where('is_inline', true)->first()->isCandidate())->toBeFalse()
        ->and($mail->events()->where('step', 'ingest')->exists())->toBeTrue();

    expect($service->ingest('rechnungen@hwk-do.de', 'msg-1', InvoiceMailSource::Webhook))->toBeNull()
        ->and(InvoiceMail::query()->count())->toBe(1);
});

it('wählt posteingang, einvoice und gelöschte objekte für den import vor', function (): void {
    expect(\Hwkdo\IntranetAppAssets\Support\InvoiceMailImportFolders::shouldPreselect('Posteingang', 0))->toBeTrue()
        ->and(\Hwkdo\IntranetAppAssets\Support\InvoiceMailImportFolders::shouldPreselect('eInvoice', 1))->toBeTrue()
        ->and(\Hwkdo\IntranetAppAssets\Support\InvoiceMailImportFolders::shouldPreselect('Gelöschte Objekte', 0))->toBeTrue()
        ->and(\Hwkdo\IntranetAppAssets\Support\InvoiceMailImportFolders::shouldPreselect('Gelöschte Elemente', 0))->toBeTrue()
        ->and(\Hwkdo\IntranetAppAssets\Support\InvoiceMailImportFolders::shouldPreselect('Gesendete Elemente', 0))->toBeFalse()
        ->and(\Hwkdo\IntranetAppAssets\Support\InvoiceMailImportFolders::shouldPreselect('Posteingang', 1))->toBeFalse();
});

it('hält den unique-lock der ingest-jobs unter der cache-key-grenze', function (): void {
    $messageId = str_repeat('A', 152);
    $job = new IngestInvoiceMailJob(messageId: $messageId);

    $lockKey = config('cache.prefix').UniqueLock::getKey($job);

    expect(strlen($lockKey))->toBeLessThanOrEqual(255)
        ->and($job->uniqueId())->toBe(hash('sha256', $messageId))
        ->and($job->uniqueId())->not->toBe($messageId);
});

it('liest die message-id aus der graph-notification', function (): void {
    expect(IngestInvoiceMailJob::messageIdFromNotification(['resourceData' => ['id' => 'AAMk1']]))->toBe('AAMk1')
        ->and(IngestInvoiceMailJob::messageIdFromNotification(['resource' => 'Users/abc/Messages/AAMk2']))->toBe('AAMk2')
        ->and(IngestInvoiceMailJob::messageIdFromNotification([]))->toBeNull();
});

it('filtert automatische antworten, sperrliste und mails ohne beleg ohne ki', function (): void {
    $classifier = app(InvoiceMailClassifier::class);

    $autoReply = makeInvoiceMail(['subject' => 'Automatische Antwort: Rechnung']);
    makeInvoiceAttachment($autoReply);
    expect($classifier->hardFilterReason($autoReply->load('attachments')))->toBe('Automatische Antwort oder Systemnachricht');

    $blocked = makeInvoiceMail(['from_address' => 'news@spam.example']);
    makeInvoiceAttachment($blocked);
    expect($classifier->hardFilterReason($blocked->load('attachments'), ['@spam.example']))->toBe('Absender steht auf der Sperrliste');

    $withoutDocument = makeInvoiceMail();
    expect($classifier->hardFilterReason($withoutDocument->load('attachments')))->toBe('Kein PDF- oder Bildanhang');
});

it('stuft per jev als relevant ein und startet die anhang-analyse', function (): void {
    Queue::fake();

    $this->mock(InvoiceMailDocumentReader::class, function ($mock): void {
        $mock->shouldReceive('firstPageImage')->andReturn(null);
        $mock->shouldReceive('cleanupImage');
    });
    $this->mock(IntranetAiGatewayInterface::class, function ($mock): void {
        $mock->shouldReceive('classify')->once()->andReturn(new AiClassificationResult(
            probabilities: ['relevant' => 0.97],
            choices: ['kind' => ['choice' => 'invoice', 'probabilities' => ['invoice' => 0.9], 'confidence' => 0.9]],
            provider: 'typesafe',
            model: 'jev-latest',
        ));
    });

    $mail = makeInvoiceMail();
    $attachment = makeInvoiceAttachment($mail);
    $logo = makeInvoiceAttachment($mail, ['filename' => 'logo.png', 'mime_type' => 'image/png', 'is_inline' => true, 'size' => 500]);

    (new ClassifyInvoiceMailJob($mail->id))->handle(app(InvoiceMailClassifier::class));

    $mail->refresh();
    expect($mail->is_relevant)->toBeTrue()
        ->and($mail->relevance_probability)->toBe(0.97)
        ->and($mail->relevance_kind)->toBe('invoice')
        ->and($mail->status)->toBe(InvoiceMailStatus::Analyzing)
        ->and($logo->refresh()->status)->toBe(InvoiceMailAttachmentStatus::Skipped);

    Queue::assertPushed(AnalyzeInvoiceMailAttachmentJob::class, fn (AnalyzeInvoiceMailAttachmentJob $job): bool => $job->attachmentId === $attachment->id);
});

it('markiert mails unter der schwelle als irrelevant', function (): void {
    Queue::fake();
    invoiceMailSettings(['invoiceMailRelevanceThreshold' => 0.9]);

    $this->mock(InvoiceMailDocumentReader::class, function ($mock): void {
        $mock->shouldReceive('firstPageImage')->andReturn(null);
        $mock->shouldReceive('cleanupImage');
    });
    $this->mock(IntranetAiGatewayInterface::class, function ($mock): void {
        $mock->shouldReceive('classify')->andReturn(new AiClassificationResult(probabilities: ['relevant' => 0.4]));
    });

    $mail = makeInvoiceMail();
    makeInvoiceAttachment($mail);

    (new ClassifyInvoiceMailJob($mail->id))->handle(app(InvoiceMailClassifier::class));

    $mail->refresh();
    expect($mail->is_relevant)->toBeFalse()
        ->and($mail->status)->toBe(InvoiceMailStatus::Irrelevant)
        ->and($mail->threshold_used)->toBe(0.9);

    Queue::assertNotPushed(AnalyzeInvoiceMailAttachmentJob::class);
});

it('lässt eine sichere rechnung trotz schwelle durch', function (): void {
    Queue::fake();
    invoiceMailSettings(['invoiceMailRelevanceThreshold' => 0.85]);

    $this->mock(InvoiceMailDocumentReader::class, function ($mock): void {
        $mock->shouldReceive('firstPageImage')->andReturn(null);
        $mock->shouldReceive('cleanupImage');
    });
    $this->mock(IntranetAiGatewayInterface::class, function ($mock): void {
        $mock->shouldReceive('classify')->andReturn(new AiClassificationResult(
            probabilities: ['relevant' => 0.71],
            choices: ['kind' => ['choice' => 'invoice', 'probabilities' => ['invoice' => 1], 'confidence' => 1]],
        ));
    });

    $mail = makeInvoiceMail();
    makeInvoiceAttachment($mail);

    (new ClassifyInvoiceMailJob($mail->id))->handle(app(InvoiceMailClassifier::class));

    $mail->refresh();
    expect($mail->is_relevant)->toBeTrue()
        ->and($mail->status)->toBe(InvoiceMailStatus::Analyzing)
        ->and($mail->events()->where('step', 'classify')->first()?->message)->toContain('Belegart ist sicher');

    Queue::assertPushed(AnalyzeInvoiceMailAttachmentJob::class);
});

it('hält sonstiges und unsichere belegarten unter der schwelle für irrelevant', function (string $kind, float $kindProbability): void {
    Queue::fake();
    invoiceMailSettings(['invoiceMailRelevanceThreshold' => 0.85]);

    $this->mock(InvoiceMailDocumentReader::class, function ($mock): void {
        $mock->shouldReceive('firstPageImage')->andReturn(null);
        $mock->shouldReceive('cleanupImage');
    });
    $this->mock(IntranetAiGatewayInterface::class, function ($mock) use ($kind, $kindProbability): void {
        $mock->shouldReceive('classify')->andReturn(new AiClassificationResult(
            probabilities: ['relevant' => 0.71],
            choices: ['kind' => ['choice' => $kind, 'probabilities' => [$kind => $kindProbability], 'confidence' => $kindProbability]],
        ));
    });

    $mail = makeInvoiceMail();
    makeInvoiceAttachment($mail);

    (new ClassifyInvoiceMailJob($mail->id))->handle(app(InvoiceMailClassifier::class));

    expect($mail->refresh()->is_relevant)->toBeFalse()
        ->and($mail->status)->toBe(InvoiceMailStatus::Irrelevant);

    Queue::assertNotPushed(AnalyzeInvoiceMailAttachmentJob::class);
})->with([
    'sonstiges' => ['other', 1.0],
    'unsichere rechnung' => ['invoice', 0.5],
]);

it('erkennt begleitdokumente am dateinamen und am textanfang', function (): void {
    expect(InvoiceMailAccompanyingDocument::matchesFilename('AGB-Kost_08_2018_Email.pdf'))->toBeTrue()
        ->and(InvoiceMailAccompanyingDocument::matchesFilename('Rechnung_RE26_01015.pdf'))->toBeFalse()
        ->and(InvoiceMailAccompanyingDocument::matchesFilename('Datenschutzerklaerung.pdf'))->toBeTrue()
        ->and(InvoiceMailAccompanyingDocument::matchesText("Allgemeine Geschäfts-, Zahlungs- und Lieferungsbedingungen\n\nI. Geltungsbereich"))->toBeTrue()
        ->and(InvoiceMailAccompanyingDocument::matchesText('Rechnung RE26/01015 vom 28.10.2026'))->toBeFalse();
});

it('stuft eine agb anhand des dateinamens als sonstiges ein', function (): void {
    $this->mock(IntranetAiGatewayInterface::class, function ($mock): void {
        $mock->shouldNotReceive('agent');
    });

    $attachment = makeInvoiceAttachment(makeInvoiceMail(), ['filename' => 'AGB-Kost_08_2018_Email.pdf']);

    app(InvoiceMailAttachmentAnalyzer::class)->analyze($attachment);

    $attachment->refresh();
    expect($attachment->document_kind)->toBe('other')
        ->and($attachment->status)->toBe(InvoiceMailAttachmentStatus::Analyzed)
        ->and($attachment->agent_result['reasoning'])->toContain('Dateiname');
});

it('korrigiert eine agb, die das modell als rechnung bezeichnet', function (): void {
    $this->mock(InvoiceMailDocumentReader::class, function ($mock): void {
        $mock->shouldReceive('text')->andReturn([
            'text' => "Allgemeine Geschäfts-, Zahlungs- und Lieferungsbedingungen\n\nI. Geltungsbereich",
            'source' => 'pdf_text',
        ]);
    });
    $this->mock(IntranetAiGatewayInterface::class, function ($mock): void {
        $mock->shouldReceive('agent')->once()->andReturn([
            'document_kind' => 'invoice',
            'reasoning' => 'Die Mail ging an rechnungen@hwk-do.de.',
            'confidence' => 0.99,
            'recipients' => [],
        ]);
    });

    $attachment = makeInvoiceAttachment(makeInvoiceMail(), ['filename' => 'Anhang.pdf']);

    app(InvoiceMailAttachmentAnalyzer::class)->analyze($attachment);

    $attachment->refresh();
    expect($attachment->document_kind)->toBe('other')
        ->and($attachment->agent_result['reasoning'])->toContain('Anhangtext');
});

it('ordnet empfänger über e-mail und ldap-anzeigenamen eindeutig zu', function (): void {
    $anna = User::factory()->create([
        'active' => true,
        'vorname' => 'Anna',
        'nachname' => 'Müllerova',
        'email' => 'anna.muellerova@hwk-do.de',
    ]);
    User::factory()->create(['active' => true, 'vorname' => 'Bernd', 'nachname' => 'Schmitzke']);

    rememberLdapDisplayName($anna, 'Müllerova, Anna');

    $matcher = app(InvoiceMailRecipientMatcher::class);

    $byEmail = $matcher->match([['email' => 'Anna.Muellerova@hwk-do.de', 'weight' => 1]], 0.9);
    expect($byEmail['status'])->toBe(InvoiceMailMatchStatus::Unique)
        ->and($byEmail['user_id'])->toBe($anna->id);

    $byName = $matcher->match([['name' => 'Frau Müllerova, Anna', 'weight' => 0.9]], 0.9);
    expect($byName['status'])->toBe(InvoiceMailMatchStatus::Unique)
        ->and($byName['user_id'])->toBe($anna->id);

    expect($matcher->match([['name' => 'Niemand Unbekannt', 'weight' => 1]], 0.9)['status'])->toBe(InvoiceMailMatchStatus::None);
});

it('meldet mehrdeutig, wenn nur ein häufiger nachname bekannt ist', function (): void {
    $anna = User::factory()->create(['active' => true, 'vorname' => 'Anna', 'nachname' => 'Doppelname']);
    $bernd = User::factory()->create(['active' => true, 'vorname' => 'Bernd', 'nachname' => 'Doppelname']);
    rememberLdapDisplayName($anna, 'Doppelname, Anna');
    rememberLdapDisplayName($bernd, 'Doppelname, Bernd');

    $result = app(InvoiceMailRecipientMatcher::class)->match([['name' => 'Herr Doppelname', 'weight' => 1]], 0.9);

    expect($result['status'])->toBe(InvoiceMailMatchStatus::Ambiguous)
        ->and($result['user_id'])->toBeNull()
        ->and($result['candidates'])->toHaveCount(2);
});

it('lädt im report-modus nie automatisch hoch', function (): void {
    $user = User::factory()->create(['active' => true]);
    $analyzer = app(InvoiceMailAttachmentAnalyzer::class);

    $mail = makeInvoiceMail(['report_mode' => true]);
    $attachment = makeInvoiceAttachment($mail, [
        'document_kind' => 'invoice',
        'status' => InvoiceMailAttachmentStatus::Analyzed,
        'match_status' => InvoiceMailMatchStatus::Unique,
        'suggested_user_id' => $user->id,
        'suggested_confidence' => 0.99,
    ]);

    expect($analyzer->shouldAutoUpload($attachment))->toBeFalse();

    invoiceMailSettings(['invoiceMailReportMode' => false]);
    $newMail = makeInvoiceMail(['report_mode' => false]);
    $newAttachment = makeInvoiceAttachment($newMail, [
        'document_kind' => 'delivery_note',
        'status' => InvoiceMailAttachmentStatus::Analyzed,
        'match_status' => InvoiceMailMatchStatus::Unique,
        'suggested_user_id' => $user->id,
        'suggested_confidence' => 0.99,
    ]);

    expect($analyzer->shouldAutoUpload($newAttachment))->toBeTrue()
        ->and($analyzer->shouldAutoUpload($attachment->refresh()))->toBeFalse();

    $newAttachment->update(['suggested_confidence' => 0.5]);
    expect($analyzer->shouldAutoUpload($newAttachment->refresh()))->toBeFalse();

    $newAttachment->update(['suggested_confidence' => 0.99, 'corrected_user_id' => $user->id]);
    expect($analyzer->shouldAutoUpload($newAttachment->refresh()))->toBeFalse();

    $newAttachment->update(['corrected_user_id' => null, 'document_kind' => 'other']);
    expect($analyzer->shouldAutoUpload($newAttachment->refresh()))->toBeFalse();
});

it('setzt wfl_empfaenger und benutzer auf den ldap-anzeigenamen', function (): void {
    D3RestLaravel::shouldReceive('getUserInGroupsSoapCached')->andReturn(['IT-Abteilung']);

    $user = User::factory()->create(['active' => true, 'vorname' => 'Anna', 'nachname' => 'Müller', 'username' => 'amueller']);
    rememberLdapDisplayName($user, 'Müller, Anna');
    $mail = makeInvoiceMail();
    $attachment = makeInvoiceAttachment($mail, [
        'document_kind' => 'invoice',
        'agent_result' => [
            'supplier' => ['name' => 'Lieferant GmbH', 'number' => 'L-1'],
            'invoice' => ['number' => 'RE-4711', 'date' => '2026-10-01', 'net' => 100, 'tax' => 19, 'gross' => 119],
        ],
    ]);

    $fields = app(InvoiceMailD3UploadService::class)->fieldsFor($attachment, $user);

    expect($fields['WFL_Empfaenger'])->toBe('Müller, Anna')
        ->and($fields['Benutzer'])->toBe(['Müller, Anna'])
        ->and($fields['Abteilung'])->toBe(['IT-Abteilung'])
        ->and($fields['Belegtyp_ZB'])->toBe('Rechnung')
        ->and($fields['Rechnungsnummer'])->toBe('RE-4711')
        ->and($fields['Bruttobetrag'])->toBe('119,00')
        ->and($fields['Belegdatum'])->toBe('2026-10-01');
});

it('baut für einen lieferschein das d3-modell lieferschein', function (): void {
    D3RestLaravel::shouldReceive('getUserInGroupsSoapCached')->andReturn(['Lager']);

    $user = User::factory()->create(['active' => true, 'vorname' => 'Anna', 'nachname' => 'Müller', 'username' => 'amueller']);
    rememberLdapDisplayName($user, 'Müller, Anna');
    $attachment = makeInvoiceAttachment(makeInvoiceMail(), [
        'document_kind' => 'delivery_note',
        'agent_result' => [
            'supplier' => ['name' => 'Lieferant GmbH', 'number' => 'L-9'],
            'invoice' => ['number' => 'LS-15', 'order_number' => 'BEN-4400', 'date' => '2026-03-02', 'subject' => 'Lieferung Schrauben'],
        ],
    ]);

    $fields = app(InvoiceMailD3UploadService::class)->fieldsFor($attachment, $user);

    expect($fields['doc_type'])->toBe(\Hwkdo\D3RestLaravel\Enums\DocTypeEnum::Lieferschein)
        ->and($fields['Lieferscheinnummer'])->toBe('LS-15')
        ->and($fields['Nummer'])->toBe(4400)
        ->and($fields['Benutzer'])->toBe(['Müller, Anna'])
        ->and($fields['Abteilung'])->toBe(['Lager'])
        ->and($fields['haushaltsjahr'])->toBe(2026)
        ->and($fields)->not->toHaveKey('WFL_Empfaenger');
});

it('lehnt eine unbekannte belegart ab', function (): void {
    $user = User::factory()->create(['active' => true, 'vorname' => 'Anna', 'nachname' => 'Müller']);
    rememberLdapDisplayName($user, 'Müller, Anna');
    $attachment = makeInvoiceAttachment(makeInvoiceMail(), ['document_kind' => 'other']);

    app(InvoiceMailD3UploadService::class)->fieldsFor($attachment, $user);
})->throws(RuntimeException::class, 'Belegart');

it('lehnt den upload ohne ldap-anzeigenamen ab', function (): void {
    $user = User::factory()->create(['active' => true, 'vorname' => null, 'nachname' => null, 'username' => null]);
    $attachment = makeInvoiceAttachment(makeInvoiceMail(), ['document_kind' => 'invoice']);

    app(InvoiceMailD3UploadService::class)->fieldsFor($attachment, $user);
})->throws(RuntimeException::class, 'WFL_Empfaenger');

it('verschiebt die mail und merkt sich die neue message-id', function (): void {
    $mailbox = new FakeInvoiceMailbox;
    app()->instance(InvoiceMailboxClientInterface::class, $mailbox);
    invoiceMailSettings([
        'invoiceMailMailboxAction' => InvoiceMailMailboxAction::MoveToFolder->value,
        'invoiceMailMoveFolderId' => 'folder-1',
    ]);

    $mail = makeInvoiceMail(['graph_message_id' => 'msg-move']);
    app(InvoiceMailMailboxActionService::class)->apply($mail);

    expect($mailbox->moved)->toBe([['msg-move', 'folder-1']])
        ->and($mail->refresh()->graph_message_id)->toBe('moved-msg-move')
        ->and($mail->mailbox_action_at)->not->toBeNull();

    app(InvoiceMailMailboxActionService::class)->apply($mail);
    expect($mailbox->moved)->toHaveCount(1);
});

it('macht standardmäßig nichts im postfach', function (): void {
    $mailbox = new FakeInvoiceMailbox;
    app()->instance(InvoiceMailboxClientInterface::class, $mailbox);

    $mail = makeInvoiceMail();
    app(InvoiceMailMailboxActionService::class)->apply($mail);

    expect($mailbox->moved)->toBe([])
        ->and($mailbox->read)->toBe([])
        ->and($mail->refresh()->mailbox_action_at)->toBeNull();
});

it('zeigt liste und detail nur mit manage-recht', function (): void {
    $mail = makeInvoiceMail();
    makeInvoiceAttachment($mail);

    $viewer = User::factory()->create(['active' => true]);
    $viewer->givePermissionTo('see-app-assets');
    $this->actingAs($viewer)->get(route('apps.assets.invoice-mails'))->assertForbidden();

    $admin = User::factory()->create(['active' => true]);
    $admin->givePermissionTo(['see-app-assets', 'manage-app-assets']);

    $this->actingAs($admin)->get(route('apps.assets.invoice-mails'))
        ->assertOk()
        ->assertSee('Rechnung 4711');

    $this->actingAs($admin)->get(route('apps.assets.invoice-mails.show', $mail))
        ->assertOk()
        ->assertSee('rechnung.pdf');
});
