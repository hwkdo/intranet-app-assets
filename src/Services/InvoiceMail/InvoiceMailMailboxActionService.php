<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services\InvoiceMail;

use Hwkdo\IntranetAppAssets\Contracts\InvoiceMailboxClientInterface;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailEventLevel;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailMailboxAction;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Models\InvoiceMail;

class InvoiceMailMailboxActionService
{
    public function __construct(private readonly InvoiceMailboxClientInterface $mailbox) {}

    /**
     * Führt die eingestellte Postfach-Aktion einmal pro Mail aus.
     */
    public function apply(InvoiceMail $mail): void
    {
        if ($mail->mailbox_action_at !== null) {
            return;
        }

        $settings = IntranetAppAssetsSettings::resolvedAppSettings();
        $action = $settings->invoiceMailMailboxAction;

        if ($action === InvoiceMailMailboxAction::None) {
            return;
        }

        if ($action === InvoiceMailMailboxAction::MarkRead) {
            $this->mailbox->markAsRead($mail->mailbox_upn, $mail->graph_message_id);
            $mail->update(['mailbox_action' => $action, 'mailbox_action_at' => now()]);
            $mail->logEvent('mailbox_action', 'Im Postfach als gelesen markiert.');

            return;
        }

        $folderId = trim($settings->invoiceMailMoveFolderId);
        if ($folderId === '') {
            $mail->logEvent('mailbox_action', 'Verschieben eingestellt, aber kein Zielordner gewählt.', InvoiceMailEventLevel::Warning);

            return;
        }

        $newId = $this->mailbox->move($mail->mailbox_upn, $mail->graph_message_id, $folderId);
        $mail->update([
            'graph_message_id' => $newId,
            'mailbox_action' => $action,
            'mailbox_action_at' => now(),
        ]);
        $mail->logEvent('mailbox_action', 'In den Zielordner verschoben.', context: ['folder_id' => $folderId]);
    }
}
