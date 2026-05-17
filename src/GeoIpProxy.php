<?php

use GeoIp2\Database\Reader;

function prepareIpGeoReader(): array
{
    // This method is now returns GeoIp2\Database\Reader objects
    // This may change in the future

    try {
        $cityDb = new Reader(GEOIP_PARENT . '/GeoLite2-City.mmdb');
    }
    catch (Exception $ex) {
        if (IS_SENTRY_USABLE) {
            \Sentry\captureException($ex);
        }
        $cityDb = null;
    }

    try {
        $asnDb = new Reader(GEOIP_PARENT . '/GeoLite2-ASN.mmdb');
    }
    catch (Exception $ex) {
        if (IS_SENTRY_USABLE) {
            \Sentry\captureException($ex);
        }
        $asnDb = null;
    }

    return [
        'cityDb' => $cityDb,
        'asnDb' => $asnDb,
    ];
}


function getIPGeoDataCity(Reader $reader, string $ip): array
{
    try {
        $cityRecord = $reader->city($ip);
    }
    catch (\GeoIp2\Exception\AddressNotFoundException $ex) {
        return [
            'countryCode' => null,
            'countryName' => null,
            'cityName' => null,
        ];
    }
    catch (Exception $ex) {
        if (IS_SENTRY_USABLE) {
            \Sentry\captureException($ex);
        }
        return [
            'countryCode' => null,
            'countryName' => null,
            'cityName' => null,
        ];
    }

    $countryCode = $cityRecord->country->isoCode ?? null;
    $countryName = $cityRecord->country->name ?? null;
    $cityName = $cityRecord->city->name ?? null;

    return [
        'countryCode' => $countryCode,
        'countryName' => $countryName,
        'cityName' => $cityName,
    ];
}

function getIPGeoDataAsn(Reader $reader, string $ip): array
{
    try {
        $asnRecord = $reader->asn($ip);
    }
    catch (\GeoIp2\Exception\AddressNotFoundException $ex) {
        return [
            'asn' => null,
            'asName' => null,
        ];
    }
    catch (Exception $ex) {
        if (IS_SENTRY_USABLE) {
            \Sentry\captureException($ex);
        }
        return [
            'asn' => null,
            'asName' => null,
        ];
    }

    $asn = $asnRecord->autonomousSystemNumber ?? null;
    $asName = $asnRecord->autonomousSystemOrganization ?? null;

    return [
        'asn' => $asn,
        'asName' => $asName,
    ];
}

function getIpGeoData(array $reader_contains_array, string $ip): array
{
    $returnData = [
        'countryCode' => null,
        'countryName' => null,
        'cityName' => null,
        'asn' => null,
        'asName' => null,
    ];

    if ($reader_contains_array['cityDb'] !== null) {
        $cityData = getIpGeoDataCity($reader_contains_array['cityDb'], $ip);
        $returnData['countryCode'] = $cityData['countryCode'];
        $returnData['countryName'] = $cityData['countryName'];
        $returnData['cityName'] = $cityData['cityName'];
    }

    if ($reader_contains_array['asnDb'] !== null) {
        $asnData = getIpGeoDataAsn($reader_contains_array['asnDb'], $ip);
        $returnData['asn'] = $asnData['asn'];
        $returnData['asName'] = $asnData['asName'];
    }

    return $returnData;
}
