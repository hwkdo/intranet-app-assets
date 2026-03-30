<?php

namespace Hwkdo\IntranetAppAssets\Support;

class OwnerChangeActionResolver
{
    /**
     * @param  array{
     *     has_pending_return: bool,
     *     has_open_handover: bool,
     *     has_rejected_handover: bool,
     *     is_clarification: bool,
     *     pending_return_href?: string|null,
     *     open_handover_href?: string|null,
     *     rejected_handover_href?: string|null,
     *     clarification_href?: string|null
     * }  $state
     * @return array{label: string, href: string, hint: string}|null
     */
    public static function resolve(array $state): ?array
    {
        if ($state['has_pending_return'] && filled($state['pending_return_href'] ?? null)) {
            return [
                'label' => 'Offene Rückgabe bearbeiten',
                'href' => (string) $state['pending_return_href'],
                'hint' => 'Über diesen Vorgang kann ein neuer Besitzer zugewiesen werden.',
            ];
        }

        if ($state['has_open_handover'] && filled($state['open_handover_href'] ?? null)) {
            return [
                'label' => 'Offene Übergabe bearbeiten',
                'href' => (string) $state['open_handover_href'],
                'hint' => 'Die offene Übergabe kann adminseitig aufgelöst werden.',
            ];
        }

        if ($state['has_rejected_handover'] && filled($state['rejected_handover_href'] ?? null)) {
            return [
                'label' => 'Abgelehnte Übergabe bearbeiten',
                'href' => (string) $state['rejected_handover_href'],
                'hint' => 'Die abgelehnte Übergabe kann mit neuem Besitzer oder Standort aufgelöst werden.',
            ];
        }

        if ($state['is_clarification'] && filled($state['clarification_href'] ?? null)) {
            return [
                'label' => 'Klärungsfall bearbeiten',
                'href' => (string) $state['clarification_href'],
                'hint' => 'Im Klärungsfall kann die Zuordnung durch die IT angepasst werden.',
            ];
        }

        return null;
    }
}
