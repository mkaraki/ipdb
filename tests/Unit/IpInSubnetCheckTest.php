<?php

require_once __DIR__ . "/../../src/IpInSubnetCheck.php";

describe('CheckIpInSubnet', function () {
    it('returns true when an IPv4 address belongs to the subnet', function () {
        expect(CheckIpInSubnet(
            '192.168.1.100',
            '192.168.1.0',
            24
        ))->toBeTrue();
    });

    it('returns false when an IPv4 address does not belong to the subnet', function () {
        expect(CheckIpInSubnet(
            '192.168.2.100',
            '192.168.1.0',
            24
        ))->toBeFalse();
    });

    it('returns true for the first address in a subnet', function () {
        expect(CheckIpInSubnet(
            '192.168.1.0',
            '192.168.1.0',
            24
        ))->toBeTrue();
    });

    it('returns true for the last address in a subnet', function () {
        expect(CheckIpInSubnet(
            '192.168.1.255',
            '192.168.1.0',
            24
        ))->toBeTrue();
    });

    it('returns false for an address immediately outside the subnet', function () {
        expect(CheckIpInSubnet(
            '192.168.2.0',
            '192.168.1.0',
            24
        ))->toBeFalse();
    });

    it('works with a /16 subnet', function () {
        expect(CheckIpInSubnet(
            '172.16.100.200',
            '172.16.0.0',
            16
        ))->toBeTrue();

        expect(CheckIpInSubnet(
            '172.17.0.1',
            '172.16.0.0',
            16
        ))->toBeFalse();
    });

    it('works with a /8 subnet', function () {
        expect(CheckIpInSubnet(
            '10.255.255.255',
            '10.0.0.0',
            8
        ))->toBeTrue();

        expect(CheckIpInSubnet(
            '11.0.0.1',
            '10.0.0.0',
            8
        ))->toBeFalse();
    });

    it('works with non-octet CIDR masks', function () {
        expect(CheckIpInSubnet(
            '192.168.1.100',
            '192.168.1.64',
            26
        ))->toBeTrue();

        expect(CheckIpInSubnet(
            '192.168.1.127',
            '192.168.1.64',
            26
        ))->toBeTrue();

        expect(CheckIpInSubnet(
            '192.168.1.128',
            '192.168.1.64',
            26
        ))->toBeFalse();
    });

    it('works with a /32 host mask', function () {
        expect(CheckIpInSubnet(
            '192.168.1.100',
            '192.168.1.100',
            32
        ))->toBeTrue();

        expect(CheckIpInSubnet(
            '192.168.1.101',
            '192.168.1.100',
            32
        ))->toBeFalse();
    });

    it('works with IPv6 addresses', function () {
        expect(CheckIpInSubnet(
            '2001:db8::1234',
            '2001:db8::',
            64
        ))->toBeTrue();

        expect(CheckIpInSubnet(
            '2001:db9::1234',
            '2001:db8::',
            64
        ))->toBeFalse();
    });

    it('works with IPv6 non-octet CIDR masks', function () {
        expect(CheckIpInSubnet(
            '2001:db8:abc0::1',
            '2001:db8:abc0::',
            52
        ))->toBeTrue();

        expect(CheckIpInSubnet(
            '2001:db8:abcd::1',
            '2001:db8:abc0::',
            52
        ))->toBeFalse();
    });
});
