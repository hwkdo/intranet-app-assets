<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Http\Controllers;

use Hwkdo\IntranetAppAssets\Models\InvoiceMailAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceMailAttachmentDownloadController
{
    public function __invoke(Request $request, int $invoiceMailAttachment): StreamedResponse
    {
        $attachment = InvoiceMailAttachment::query()->findOrFail($invoiceMailAttachment);
        $disk = Storage::disk('local');

        abort_unless(is_string($attachment->path) && $disk->exists($attachment->path), 404);

        $inline = in_array($attachment->mime_type, InvoiceMailAttachment::CandidateMimeTypes, true);
        $headers = [
            'Content-Type' => $inline ? $attachment->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $request->boolean('download') || ! $inline
            ? $disk->download($attachment->path, $attachment->filename, $headers)
            : $disk->response($attachment->path, $attachment->filename, $headers);
    }
}
