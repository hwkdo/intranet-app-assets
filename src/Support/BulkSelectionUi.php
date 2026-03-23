<?php

namespace Hwkdo\IntranetAppAssets\Support;

/**
 * Browser-Event für Alpine: Auswahl-Zähler sofort leeren (z. B. nach Admin-Bulk-Commit oder „Auswahl leeren“ mit skipRender).
 */
final class BulkSelectionUi
{
    public const CLEAR_SELECTED_IDS_EVENT = 'intranet-app-assets-bulk-selection-clear';

    /**
     * JavaScript-Ausdruck für Livewire {@see \Livewire\Component::js()}: leert optimistische Alpine-Auswahl.
     */
    public static function livewireClearSelectionJs(): string
    {
        return 'window.dispatchEvent(new CustomEvent('.json_encode(self::CLEAR_SELECTED_IDS_EVENT).'))';
    }
}
