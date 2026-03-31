@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => [],
    'renderAppIndexAuto' => null,
])

@php
    $defaultNavItems = [
        ['label' => 'Übersicht', 'href' => route('apps.assets.index'), 'icon' => 'home', 'description' => 'Zurück zur Übersicht', 'buttonText' => 'Übersicht anzeigen'],
        ['label' => 'Suche', 'href' => route('apps.assets.search'), 'icon' => 'magnifying-glass', 'description' => 'Asset-Suche über Stammdaten, Verlauf und Notizen', 'buttonText' => 'Suche öffnen'],
        ['label' => 'Meine Assets', 'href' => route('apps.assets.meine-assets'), 'icon' => 'user', 'description' => 'Ihre zugewiesenen Assets', 'buttonText' => 'Meine Assets anzeigen'],
        ['label' => 'Alle Assets', 'href' => route('apps.assets.liste'), 'icon' => 'server-stack', 'description' => 'Alle Assets durchsuchen und verwalten', 'buttonText' => 'Assets anzeigen'],
        ['label' => 'Mobilgeräte', 'href' => route('apps.assets.mobilgeraete'), 'icon' => 'device-phone-mobile', 'description' => 'Mobilgeräte (is_intune_object) mit IMEI und Intune-Filter', 'buttonText' => 'Mobilgeräte anzeigen'],
        ['label' => 'Domänengeräte', 'href' => route('apps.assets.domaenengeraete'), 'icon' => 'computer-desktop', 'description' => 'Domänengeräte (is_domain_object) mit Domäne und Last Logon', 'buttonText' => 'Domänengeräte anzeigen'],
        ['label' => 'Domain-Abgleich', 'href' => route('apps.assets.domain-compare'), 'icon' => 'arrows-right-left', 'description' => 'Vergleich: Assets vs. Computer in AD (Verwaltung/Schulung)', 'buttonText' => 'Domain-Abgleich anzeigen'],
        ['label' => 'Itexia-Geräte', 'href' => route('apps.assets.itexiageraete'), 'icon' => 'qr-code', 'description' => 'Assets mit Itexia-ID und Filter Gefunden/Nicht gefunden in Itexia', 'buttonText' => 'Itexia-Geräte anzeigen'],
        ['label' => 'Legacy-Assets', 'href' => route('apps.assets.legacy-assets'), 'icon' => 'circle-stack', 'description' => 'Legacy-Bestand vergleichen und fehlende Assets nachimportieren', 'buttonText' => 'Legacy-Assets anzeigen', 'permission' => 'manage-app-assets'],
        ['label' => 'Fehlende Rechnung', 'href' => route('apps.assets.fehlende-rechnung'), 'icon' => 'document-text', 'description' => 'Assets mit fehlender Rechnungsnummer (manuell oder nach Ende der automatischen BEN-Suche)', 'buttonText' => 'Fehlende Rechnung anzeigen', 'permission' => 'manage-app-assets'],
        ['label' => 'Gelöschte Assets', 'href' => route('apps.assets.deleted'), 'icon' => 'archive-box', 'description' => 'Soft-gelöschte Assets einsehen und verwalten', 'buttonText' => 'Gelöschte Assets anzeigen', 'permission' => 'manage-app-assets'],
        ['label' => 'Abgelehnte Übergaben', 'href' => route('apps.assets.admin.rejected-handovers'), 'icon' => 'x-circle', 'description' => 'Vom Empfänger abgelehnte Übergaben bearbeiten und Asset-Zuordnung klären', 'buttonText' => 'Abgelehnte Übergaben anzeigen', 'permission' => 'manage-app-assets'],
        ['label' => 'Offene Übergaben', 'href' => route('apps.assets.admin.open-handovers'), 'icon' => 'clock', 'description' => 'Noch offene Übergaben adminseitig auflösen', 'buttonText' => 'Offene Übergaben anzeigen', 'permission' => 'manage-app-assets'],
        ['label' => 'Assets in Klärung', 'href' => route('apps.assets.admin.clarifications'), 'icon' => 'question-mark-circle', 'description' => 'Vom Besitzer gemeldete Klärungsfälle bearbeiten', 'buttonText' => 'Klärfälle anzeigen', 'permission' => 'manage-app-assets'],
        ['label' => 'Offene Rückgaben', 'href' => route('apps.assets.admin.returns.pending'), 'icon' => 'arrow-uturn-left', 'description' => 'Rückgaben mit Empfangsbestätigung und Zuordnung abschließen', 'buttonText' => 'Rückgaben anzeigen', 'permission' => 'manage-app-assets'],
        ['label' => 'Meine Einstellungen', 'href' => route('apps.assets.settings.user'), 'icon' => 'cog-6-tooth', 'description' => 'Persönliche Einstellungen anpassen', 'buttonText' => 'Einstellungen öffnen'],
        ['label' => 'Admin', 'href' => route('apps.assets.admin.index'), 'icon' => 'shield-check', 'description' => 'Administrationsbereich verwalten', 'buttonText' => 'Admin öffnen', 'permission' => 'manage-app-assets'],
    ];

    $navItems = !empty($navItems) ? $navItems : $defaultNavItems;
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('assets');
@endphp

@if($customBgUrl)
    @push('app-styles')
    <style data-app-bg data-ts="{{ uniqid() }}">
        :root { --app-bg-image: url('{{ $customBgUrl }}'); }
    </style>
    @endpush
@endif

@php
    $shouldRenderAppIndexAuto = $renderAppIndexAuto ?? request()->routeIs('apps.assets.index');
@endphp

@if($shouldRenderAppIndexAuto)
    <x-intranet-app-base::app-layout
        app-identifier="assets"
        :heading="$heading"
        :subheading="$subheading"
        :nav-items="$navItems"
        :wrap-in-card="false"
    >
        <x-intranet-app-base::app-index-auto
            app-identifier="assets"
            app-name="Assets"
            app-description="Verwaltung von IT-Assets und Hardware"
            :nav-items="$navItems"
            welcome-title="Willkommen in der Asset-Verwaltung"
            welcome-description="Hier können Sie alle IT-Assets verwalten, Übergaben und Rückgaben nachverfolgen sowie die vollständige Asset-Historie einsehen."
        />
    </x-intranet-app-base::app-layout>
@else
    <x-intranet-app-base::app-layout
        app-identifier="assets"
        :heading="$heading"
        :subheading="$subheading"
        :nav-items="$navItems"
        :wrap-in-card="true"
    >
        {{ $slot }}
    </x-intranet-app-base::app-layout>
@endif
