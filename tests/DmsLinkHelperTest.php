<?php

use Hwkdo\IntranetAppAssets\Support\DmsLinkHelper;

test('baseUrlFromDmsSearchUrl returns base URL when suffix present', function () {
    $dmsSearchUrl = 'https://d3one.hwk-do.de/dms/r/254733d1-1130-5cad-becd-6ca766c084d6/sr/?fulltext=';
    expect(DmsLinkHelper::baseUrlFromDmsSearchUrl($dmsSearchUrl))
        ->toBe('https://d3one.hwk-do.de/dms/r/254733d1-1130-5cad-becd-6ca766c084d6');
});

test('baseUrlFromDmsSearchUrl returns empty string when suffix not present', function () {
    expect(DmsLinkHelper::baseUrlFromDmsSearchUrl('https://example.com/other'))
        ->toBe('');
});

test('baseUrlFromDmsSearchUrl returns empty string for empty input', function () {
    expect(DmsLinkHelper::baseUrlFromDmsSearchUrl(''))->toBe('');
});

test('invoiceUrl returns null when base URL empty', function () {
    expect(DmsLinkHelper::invoiceUrl('', 'T123'))->toBeNull();
});

test('invoiceUrl returns null when invoice number empty', function () {
    expect(DmsLinkHelper::invoiceUrl('https://d3.example/dms/r/uuid', null))->toBeNull();
    expect(DmsLinkHelper::invoiceUrl('https://d3.example/dms/r/uuid', ''))->toBeNull();
});

test('invoiceUrl returns full URL when both provided', function () {
    $base = 'https://d3one.hwk-do.de/dms/r/254733d1-1130-5cad-becd-6ca766c084d6';
    expect(DmsLinkHelper::invoiceUrl($base, 'T003693386'))
        ->toBe('https://d3one.hwk-do.de/dms/r/254733d1-1130-5cad-becd-6ca766c084d6/o2/T003693386');
});

test('orderNumberUrl returns null when base URL empty', function () {
    expect(DmsLinkHelper::orderNumberUrl('', '355624003'))->toBeNull();
});

test('orderNumberUrl returns null when order number empty', function () {
    expect(DmsLinkHelper::orderNumberUrl('https://d3.example/dms/r/uuid', null))->toBeNull();
    expect(DmsLinkHelper::orderNumberUrl('https://d3.example/dms/r/uuid', ''))->toBeNull();
});

test('orderNumberUrl returns full URL and rawurlencodes order number', function () {
    $base = 'https://d3one.hwk-do.de/dms/r/254733d1-1130-5cad-becd-6ca766c084d6';
    expect(DmsLinkHelper::orderNumberUrl($base, '355624003'))
        ->toBe('https://d3one.hwk-do.de/dms/r/254733d1-1130-5cad-becd-6ca766c084d6/sr/?fulltext=355624003');
});

test('orderNumberUrl rawurlencodes special characters in order number', function () {
    $base = 'https://d3.example/dms/r/uuid';
    $url = DmsLinkHelper::orderNumberUrl($base, 'ABC 123');
    expect($url)->toContain('fulltext=');
    expect($url)->toContain(rawurlencode('ABC 123'));
});
