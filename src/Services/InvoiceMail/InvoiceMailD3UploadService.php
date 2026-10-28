<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services\InvoiceMail;

use App\Models\User;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\D3RestLaravel\Facades\D3RestLaravel;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailD3Status;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailDocumentKind;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailEventLevel;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailAttachment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InvoiceMailD3UploadService
{
    private const SoapGroupsCacheTtlSeconds = 86400;

    public function kindFor(InvoiceMailAttachment $attachment): InvoiceMailDocumentKind
    {
        $stored = InvoiceMailDocumentKind::tryFrom((string) $attachment->document_kind);
        if ($stored !== null) {
            return $stored;
        }

        return InvoiceMailDocumentKind::tryFrom((string) ($attachment->agent_result['document_kind'] ?? ''))
            ?? InvoiceMailDocumentKind::Other;
    }

    /**
     * Felder des D3-Dokuments, wie sie hochgeladen würden (auch für die Vorschau im Upload-Modal).
     *
     * @return array<string, mixed>
     */
    public function fieldsFor(InvoiceMailAttachment $attachment, User $user): array
    {
        $kind = $this->kindFor($attachment);
        $displayName = trim((string) ($user->ldap_displayname ?? ''));
        if ($displayName === '') {
            throw new RuntimeException('Für '.$user->name.' ist kein LDAP-Anzeigename hinterlegt. WFL_Empfaenger bzw. Benutzer kann nicht gesetzt werden.');
        }

        if ($kind->d3Model() === null) {
            throw new RuntimeException('Die Belegart ist nicht festgelegt. Bitte Rechnung, Lieferschein, Bestellschein oder Angebot wählen.');
        }

        $result = $attachment->agent_result ?? [];
        $invoice = is_array($result['invoice'] ?? null) ? $result['invoice'] : [];
        $supplier = is_array($result['supplier'] ?? null) ? $result['supplier'] : [];
        $documentDate = $this->date($invoice['date'] ?? null);
        $documentNumber = $this->string($invoice['number'] ?? null);
        $subject = $this->string($invoice['subject'] ?? null);
        if ($subject === '') {
            $subject = trim($kind->label().($documentNumber !== '' ? ' '.$documentNumber : ''));
        }

        $shared = [
            'filename' => $attachment->filename,
            'supplierName' => $this->string($supplier['name'] ?? null),
            'supplierNumber' => $this->string($supplier['number'] ?? null),
            'displayName' => $displayName,
            'departments' => $this->abteilungen($user),
            'documentDate' => $documentDate,
            'documentNumber' => $documentNumber,
            'orderNumber' => $this->integerOrZero($invoice['order_number'] ?? null),
            'subject' => $subject,
            'year' => $this->year($documentDate),
            'capturedAt' => now()->format('Y-m-d'),
            'net' => $this->amount($invoice['net'] ?? null),
            'tax' => $this->amount($invoice['tax'] ?? null),
            'gross' => $this->amount($invoice['gross'] ?? null),
        ];

        return match ($kind) {
            InvoiceMailDocumentKind::DeliveryNote => $this->lieferscheinFields($shared),
            InvoiceMailDocumentKind::PurchaseOrder => $this->bestellscheinFields($shared),
            InvoiceMailDocumentKind::Quote => $this->angebotFields($shared),
            default => $this->zahlungsbelegFields($shared),
        };
    }

    /**
     * Lädt den Anhang als Zahlungsbeleg hoch und gibt die D3-Dokument-ID zurück.
     */
    public function upload(InvoiceMailAttachment $attachment, User $user, ?int $uploadedBy = null): string
    {
        $lock = Cache::lock('assets-invoice-mail-d3-upload-'.$attachment->id, 300);
        if (! $lock->get()) {
            throw new RuntimeException('Für diesen Anhang läuft bereits ein D3-Upload.');
        }

        try {
            return $this->uploadLocked($attachment->refresh(), $user, $uploadedBy);
        } finally {
            $lock->release();
        }
    }

    private function uploadLocked(InvoiceMailAttachment $attachment, User $user, ?int $uploadedBy): string
    {
        if ($attachment->d3_status === InvoiceMailD3Status::Uploaded) {
            throw new RuntimeException('Der Anhang wurde bereits nach D3 hochgeladen ('.$attachment->d3_document_id.').');
        }

        $path = $attachment->absolutePath();
        if ($path === null || ! is_file($path)) {
            throw new RuntimeException('Die lokale Kopie des Anhangs fehlt.');
        }

        $mail = $attachment->invoiceMail;
        $kind = $this->kindFor($attachment);
        $modelClass = $kind->d3Model();
        $fields = $this->fieldsFor($attachment, $user);
        $startedAt = microtime(true);

        try {
            $response = (new $modelClass($fields))->save(filepath: $path);
        } catch (\Throwable $e) {
            $this->markFailed($attachment, $e->getMessage());
            throw $e;
        }

        if (! $response->success || ! is_string($response->id) || $response->id === '') {
            $message = 'D3 hat den Upload abgelehnt: '.($response->message ?: 'ohne Meldung');
            $this->markFailed($attachment, $message);
            throw new RuntimeException($message);
        }

        $attachment->update([
            'd3_status' => InvoiceMailD3Status::Uploaded,
            'd3_document_id' => $response->id,
            'd3_uploaded_by' => $uploadedBy,
            'd3_uploaded_at' => now(),
            'd3_error' => null,
        ]);

        $mail?->logEvent(
            'd3_upload',
            'Als '.$kind->d3Label().' nach D3 hochgeladen: '.$response->id.' für '.$user->ldap_displayname.'.',
            context: ['fields' => collect($fields)->except('doc_type')->all(), 'manual' => $uploadedBy !== null],
            attachmentId: $attachment->id,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
        );

        return $response->id;
    }

    private function markFailed(InvoiceMailAttachment $attachment, string $message): void
    {
        $attachment->update([
            'd3_status' => InvoiceMailD3Status::Failed,
            'd3_error' => mb_substr($message, 0, 2000),
        ]);

        $attachment->invoiceMail?->logEvent(
            'd3_upload',
            'D3-Upload fehlgeschlagen: '.$message,
            InvoiceMailEventLevel::Error,
            attachmentId: $attachment->id,
        );
    }

    /**
     * @return array<int, string>
     */
    private function abteilungen(User $user): array
    {
        try {
            $groups = D3RestLaravel::getUserInGroupsSoapCached((string) $user->username, self::SoapGroupsCacheTtlSeconds);
            if (is_array($groups) && $groups !== []) {
                return collect($groups)->map(fn ($group): string => trim((string) $group))->filter()->unique()->values()->all();
            }
        } catch (\Throwable $e) {
            Log::warning('assets.invoice_mail.d3_groups_soap.failed', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }

        $abteilung = trim((string) ($user->abteilung ?? ''));

        return $abteilung !== '' ? [$abteilung] : [];
    }

    /**
     * @param  array<string, mixed>  $shared
     * @return array<string, mixed>
     */
    private function zahlungsbelegFields(array $shared): array
    {
        return [
            'Benutzer' => [$shared['displayName']],
            'Abteilung' => $shared['departments'],
            'Belegtyp_ZB' => $this->belegtyp(),
            'Rechnungsnummer' => $shared['documentNumber'],
            'Nettobetrag' => $shared['net'],
            'Bruttobetrag' => $shared['gross'],
            'Mehrwertsteuerbetrag' => $shared['tax'],
            'Lieferantennummer' => $shared['supplierNumber'],
            'Lieferantenname' => $shared['supplierName'],
            'Belegdatum' => $shared['documentDate'],
            'WFL_Empfaenger' => $shared['displayName'],
            'filename' => $shared['filename'],
            'doc_type' => DocTypeEnum::Zahlungsbeleg,
        ];
    }

    /**
     * @param  array<string, mixed>  $shared
     * @return array<string, mixed>
     */
    private function lieferscheinFields(array $shared): array
    {
        return [
            'betreff' => $shared['subject'],
            'Nummer' => $shared['orderNumber'],
            'Lieferscheinnummer' => $shared['documentNumber'],
            'Lieferantennummer' => $shared['supplierNumber'],
            'lieferantenName' => $shared['supplierName'],
            'lieferantenSuchfeld' => $shared['supplierNumber'] !== '' ? $shared['supplierNumber'] : $shared['supplierName'],
            'haushaltsjahr' => $shared['year'],
            'Erfassungsdatum' => $shared['capturedAt'],
            'Benutzer' => [$shared['displayName']],
            'Belegdatum' => $shared['documentDate'],
            'Abteilung' => $shared['departments'],
            'filename' => $shared['filename'],
            'doc_type' => DocTypeEnum::Lieferschein,
        ];
    }

    /**
     * @param  array<string, mixed>  $shared
     * @return array<string, mixed>
     */
    private function bestellscheinFields(array $shared): array
    {
        return [
            'nummer' => $shared['orderNumber'],
            'lieferantenName' => $shared['supplierName'],
            'lieferantenSuchfeld' => $shared['supplierNumber'] !== '' ? $shared['supplierNumber'] : $shared['supplierName'],
            'kostenstelle' => 0,
            'haushaltsjahr' => $shared['year'],
            'erfassungsdatum' => $shared['capturedAt'],
            'betreff' => $shared['subject'],
            'benutzer' => [$shared['displayName']],
            'belegdatum' => $shared['documentDate'],
            'abteilung' => $shared['departments'],
            'filename' => $shared['filename'],
            'doc_type' => DocTypeEnum::Bestellschein,
        ];
    }

    /**
     * @param  array<string, mixed>  $shared
     * @return array<string, mixed>
     */
    private function angebotFields(array $shared): array
    {
        return [
            'betreff' => $shared['subject'],
            'Nummer' => $shared['orderNumber'],
            'Erfassungsdatum' => $shared['capturedAt'],
            'Benutzer' => [$shared['displayName']],
            'Belegdatum' => $shared['documentDate'],
            'Begründung' => 'Nein',
            'Angebotsnummer' => $shared['documentNumber'] !== '' ? $shared['documentNumber'] : '-',
            'Abteilung' => $shared['departments'],
            'filename' => $shared['filename'],
            'doc_type' => DocTypeEnum::Angebote,
        ];
    }

    private function belegtyp(): string
    {
        $belegtyp = trim(IntranetAppAssetsSettings::resolvedAppSettings()->invoiceMailBelegtypDefault);

        return $belegtyp !== '' ? $belegtyp : 'Rechnung';
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? mb_substr(trim((string) $value), 0, 250) : '';
    }

    private function amount(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '';
        }

        return number_format((float) $value, 2, ',', '');
    }

    private function integerOrZero(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $digits = preg_replace('/\D+/', '', is_scalar($value) ? (string) $value : '');

        return $digits === '' ? 0 : (int) $digits;
    }

    private function year(?string $date): int
    {
        if ($date === null) {
            return (int) now()->year;
        }

        try {
            return (int) Carbon::parse($date)->year;
        } catch (\Throwable) {
            return (int) now()->year;
        }
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
