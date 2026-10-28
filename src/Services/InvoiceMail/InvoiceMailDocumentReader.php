<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services\InvoiceMail;

use App\Services\PdfService;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailAttachment;
use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use RuntimeException;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf as PdfToImage;

class InvoiceMailDocumentReader
{
    public function __construct(
        private readonly PdfService $pdfService,
        private readonly IntranetAiGatewayInterface $gateway,
    ) {}

    /**
     * Text eines Anhangs: eingebetteter PDF-Text, sonst die Document-Parse-Engine der App (z. B. Vision-OCR).
     *
     * @return array{text: string, source: string}
     */
    public function text(InvoiceMailAttachment $attachment): array
    {
        $path = $this->existingPath($attachment);

        if ($attachment->isPdf()) {
            $embedded = $this->embeddedText($path);
            if ($this->isUsable($embedded)) {
                return ['text' => $embedded, 'source' => 'pdf_text'];
            }
        }

        $parsed = trim($this->gateway->parseAppDocument($path, 'assets'));
        if (! $this->isUsable($parsed)) {
            throw new RuntimeException('Aus dem Anhang konnte kein Text gelesen werden.');
        }

        return ['text' => $parsed, 'source' => 'document_parse'];
    }

    /**
     * Erste Seite als PNG (für die Bild-Klassifikation). Aufrufer löscht die Datei.
     */
    public function firstPageImage(InvoiceMailAttachment $attachment): ?string
    {
        $path = $this->existingPath($attachment);

        if (! $attachment->isPdf()) {
            return in_array($attachment->mime_type, ['image/jpeg', 'image/png'], true) ? $path : null;
        }

        $directory = storage_path('app/private/invoice-mail-raster/'.bin2hex(random_bytes(8)));
        if (! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Verzeichnis für die PDF-Rasterung konnte nicht angelegt werden.');
        }

        $target = $directory.'/page-1.png';
        (new PdfToImage($path))
            ->format(OutputFormat::Png)
            ->resolution(120)
            ->selectPages(1)
            ->save($target);

        return is_file($target) ? $target : null;
    }

    public function cleanupImage(?string $imagePath, InvoiceMailAttachment $attachment): void
    {
        if ($imagePath === null || $imagePath === $attachment->absolutePath() || ! is_file($imagePath)) {
            return;
        }

        @unlink($imagePath);
        @rmdir(dirname($imagePath));
    }

    private function existingPath(InvoiceMailAttachment $attachment): string
    {
        $path = $attachment->absolutePath();
        if ($path === null || ! is_file($path)) {
            throw new RuntimeException('Die lokale Kopie des Anhangs fehlt.');
        }

        return $path;
    }

    private function embeddedText(string $path): string
    {
        try {
            return trim((string) $this->pdfService->pdfToText($path));
        } catch (\Throwable) {
            return '';
        }
    }

    private function isUsable(string $text): bool
    {
        return mb_strlen(trim(str_replace("\f", '', $text))) >= 30;
    }
}
