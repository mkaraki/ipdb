<?php
require_once __DIR__ . '/../../src/Atk/PostToAtk.php';

describe('Info Page: Stateless', function () {
    beforeEach(function () {
        $this->link = db_init();
        $this->link->query('TRUNCATE TABLE meta_rdns');
        $this->link->query('TRUNCATE TABLE atkIps');
    });

    afterEach(function () {
        $this->link->close();
    });

    it('test form access from app root', function () {
        $page = visit(PEST_APP_URL . '/');

        $page->type('#search-ip-box', '1.1.1.1')
             ->click('@btn-search-on-top-page')
             ->assertSee('1.1.1.1 is not found in our database');
    });

    it('check 1.1.1.1', function () {
        $page = visit(PEST_APP_URL . '/info?q=1.1.1.1');

        $page->assertSee('1.1.1.1 is not found in our database');
    });

    it('check 2606:4700:4700::1111', function () {
        $page = visit(PEST_APP_URL . '/info?q=2606:4700:4700::1111');

        $page->assertSee('2606:4700:4700::1111 is not found in our database');
    });

    it('check (long) 2606:4700:4700:0000:0000:0000:0000:1111', function () {
        $page = visit(PEST_APP_URL . '/info?q=2606:4700:4700:0000:0000:0000:0000:1111');

        $page->assertSee('2606:4700:4700:0000:0000:0000:0000:1111 is not found in our database');
    });

    it('invalid format ip', function () {
        $page = visit(PEST_APP_URL . '/info?q=1000.1.1.1');

        $page->assertSee('1000.1.1.1 is not a valid IP address.');
    });
});

describe('Info Page: Stateful', function () {
    beforeEach(function () {
        $this->link = db_init();
        $this->link->query('TRUNCATE TABLE meta_rdns');
        $this->link->query('TRUNCATE TABLE atkIps');
    });

    afterEach(function () {
        $this->link->query('TRUNCATE TABLE meta_rdns');
        $this->link->query('TRUNCATE TABLE atkIps');
        $this->link->close();
    });

    it('shows 1.1.1.1 on ATKdb with no neighbour', function() {
        $now = time();
        postToAtkDatabase($this->link, '1.1.1.1', $now);

        $page = visit(PEST_APP_URL . '/info?q=1.1.1.1');
        $page->assertSee('Reverse DNS'); // Automatically added even unable to resolve
        $page->assertSee('ATKdb'); // Found in ATKdb
        $page->assertSee('Subnets in ATK'); // Only on IPv4

        // Check first seen/last seen
        $page->assertDataAttribute('@atk-first-seen-value', 'epoch', $now);
        $page->assertDataAttribute('@atk-last-seen-value', 'epoch', $now);
    });

    it('shows 2606:4700:4700::1111 on ATKdb without neighbour', function() {
        $now = time();
        postToAtkDatabase($this->link, '2606:4700:4700::1111', $now);

        $page = visit(PEST_APP_URL . '/info?q=2606:4700:4700::1111');
        $page->assertSee('Reverse DNS'); // Automatically added even unable to resolve
        $page->assertSee('ATKdb'); // Found in ATKdb
        $page->assertDontSee('Subnets in ATK'); // Only on IPv4

        // Check first seen/last seen
        $page->assertDataAttribute('@atk-first-seen-value', 'epoch', $now);
        $page->assertDataAttribute('@atk-last-seen-value', 'epoch', $now);
    });

    it('shows 1.1.1.1 with multiple time attack on ATKdb with no neighbour', function() {
        $now = time();
        $first = $now - 10000;
        postToAtkDatabase($this->link, '1.1.1.1', $first);
        postToAtkDatabase($this->link, '1.1.1.1', $now);

        $page = visit(PEST_APP_URL . '/info?q=1.1.1.1');
        $page->assertSee('Reverse DNS'); // Automatically added even unable to resolve
        $page->assertSee('ATKdb'); // Found in ATKdb
        $page->assertSee('Subnets in ATK'); // Only on IPv4

        // Check first seen/last seen
        $page->assertDataAttribute('@atk-first-seen-value', 'epoch', $first);
        $page->assertDataAttribute('@atk-last-seen-value', 'epoch', $now);
    });

});
