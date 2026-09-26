<?php

require_once  __DIR__ . '/../../src/ReverseDns.php';

beforeEach(function () {
    /*
     * Adjust this to however your application creates a test DB connection.
     *
     * The repository tests below require a real database containing:
     *
     *   meta_rdns (
     *       ip,
     *       rdns,
     *       last_checked
     *   )
     *
     * If you already have a Pest helper that provides $this->link,
     * remove this block and use that helper instead.
     */
    $this->link = db_init();
});

afterEach(function () {
    /*
     * Only needed if you use a real database for the repository tests.
     */
    $this->link->query('TRUNCATE TABLE meta_rdns');

    Mockery::close();
});


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function createReverseDnsService(
    ?ReverseDnsRepository $repository = null,
    ?ReverseDnsResolver $resolver = null,
): ReverseDnsService {
    return new ReverseDnsService(
        $repository ?? Mockery::mock(ReverseDnsRepository::class),
        $resolver ?? Mockery::mock(ReverseDnsResolver::class),
    );
}

function insertRdnsRecord(
    $link,
    string $ip,
    ?string $rdns,
    int $lastChecked,
): void {
    $ip = formatIpForDb($ip);

    if ($rdns === null) {
        $stmt = $link->prepare(
            'INSERT INTO meta_rdns (ip, rdns, last_checked)
             VALUES (?, NULL, FROM_UNIXTIME(?))'
        );

        $stmt->bind_param('si', $ip, $lastChecked);
    } else {
        $stmt = $link->prepare(
            'INSERT INTO meta_rdns (ip, rdns, last_checked)
             VALUES (?, ?, FROM_UNIXTIME(?))'
        );

        $stmt->bind_param('ssi', $ip, $rdns, $lastChecked);
    }

    $stmt->execute();
}


/*
|--------------------------------------------------------------------------
| ReverseDnsRepository
|--------------------------------------------------------------------------
*/

describe('ReverseDnsRepository', function () {

    it('returns null when no record exists', function () {
        $repository = new ReverseDnsRepository();

        $result = $repository->find(
            $this->link,
            '8.8.8.8'
        );

        expect($result)->toBeNull();
    });


    it('returns an existing reverse DNS record', function () {
        insertRdnsRecord(
            $this->link,
            '8.8.8.8',
            'dns.google',
            time() - 100
        );

        $repository = new ReverseDnsRepository();

        $result = $repository->find(
            $this->link,
            '8.8.8.8'
        );

        expect($result)
            ->not->toBeNull()
            ->and($result['rdns'])
            ->toBe('dns.google')
            ->and($result['last_checked'])
            ->toBeInt();
    });


    it('returns the most recently checked record', function () {
        $repository = new ReverseDnsRepository();

        $repository->insert($this->link, '8.8.8.8', 'old.google.com');
        $repository->update($this->link, '8.8.8.8', 'dns.google');

        $result = $repository->find(
            $this->link,
            '8.8.8.8'
        );

        expect($result['rdns'])
            ->toBe('dns.google');
    });


    it('inserts a reverse DNS record', function () {
        $repository = new ReverseDnsRepository();

        $result = $repository->insert(
            $this->link,
            '8.8.8.8',
            'dns.google'
        );

        expect($result)->toBeTrue();

        $record = $repository->find(
            $this->link,
            '8.8.8.8'
        );

        expect($record)
            ->not->toBeNull()
            ->and($record['rdns'])
            ->toBe('dns.google');
    });


    it('inserts a record with NULL reverse DNS', function () {
        $repository = new ReverseDnsRepository();

        $result = $repository->insert(
            $this->link,
            '192.0.2.1',
            null
        );

        expect($result)->toBeTrue();

        $record = $repository->find(
            $this->link,
            '192.0.2.1'
        );

        expect($record)
            ->not->toBeNull()
            ->and($record['rdns'])
            ->toBeNull();
    });


    it('updates the reverse DNS hostname', function () {
        insertRdnsRecord(
            $this->link,
            '8.8.8.8',
            'old.example.com',
            time() - 1000
        );

        $repository = new ReverseDnsRepository();

        $result = $repository->update(
            $this->link,
            '8.8.8.8',
            'dns.google'
        );

        expect($result)->toBeTrue();

        $record = $repository->find(
            $this->link,
            '8.8.8.8'
        );

        expect($record['rdns'])
            ->toBe('dns.google');
    });


    it('updates a record and sets reverse DNS to NULL', function () {
        insertRdnsRecord(
            $this->link,
            '192.0.2.1',
            'old.example.com',
            time() - 1000
        );

        $repository = new ReverseDnsRepository();

        $result = $repository->update(
            $this->link,
            '192.0.2.1',
            null
        );

        expect($result)->toBeTrue();

        $record = $repository->find(
            $this->link,
            '192.0.2.1'
        );

        expect($record['rdns'])
            ->toBeNull();
    });


    it('updates only last_checked with touch', function () {
        $oldTimestamp = time() - 1000;

        insertRdnsRecord(
            $this->link,
            '8.8.8.8',
            'dns.google',
            $oldTimestamp
        );

        $repository = new ReverseDnsRepository();

        $before = $repository->find(
            $this->link,
            '8.8.8.8'
        );

        $result = $repository->touch(
            $this->link,
            '8.8.8.8'
        );

        $after = $repository->find(
            $this->link,
            '8.8.8.8'
        );

        expect($result)->toBeTrue();

        expect($after['rdns'])
            ->toBe('dns.google');

        expect($after['last_checked'])
            ->toBeGreaterThanOrEqual($before['last_checked']);
    });
});


/*
|--------------------------------------------------------------------------
| ReverseDnsService
|--------------------------------------------------------------------------
*/

describe('ReverseDnsService', function () {

    it('returns reverse DNS information from the repository', function () {
        $repository = Mockery::mock(ReverseDnsRepository::class);
        $resolver = Mockery::mock(ReverseDnsResolver::class);

        $expected = [
            'rdns' => 'dns.google',
            'last_checked' => time(),
        ];

        $repository
            ->shouldReceive('find')
            ->once()
            ->with($this->link, '8.8.8.8')
            ->andReturn($expected);

        $service = new ReverseDnsService(
            $repository,
            $resolver,
        );

        $result = $service->get(
            $this->link,
            '8.8.8.8'
        );

        expect($result)->toBe($expected);
    });


    it('does not resolve DNS when the cached record is fresh', function () {
        $repository = Mockery::mock(ReverseDnsRepository::class);
        $resolver = Mockery::mock(ReverseDnsResolver::class);

        $repository
            ->shouldReceive('find')
            ->once()
            ->with($this->link, '8.8.8.8')
            ->andReturn([
                'rdns' => 'dns.google',
                'last_checked' => time() - 100,
            ]);

        $resolver
            ->shouldNotReceive('resolve');

        $service = new ReverseDnsService(
            $repository,
            $resolver,
        );

        $service->update(
            $this->link,
            '8.8.8.8'
        );
    });


    it('resolves DNS when the cached record has expired', function () {
        $repository = Mockery::mock(ReverseDnsRepository::class);
        $resolver = Mockery::mock(ReverseDnsResolver::class);

        $repository
            ->shouldReceive('find')
            ->once()
            ->andReturn([
                'rdns' => 'dns.google',
                'last_checked' => time() - TTL_REVERSE_DNS - 1,
            ]);

        $resolver
            ->shouldReceive('resolve')
            ->once()
            ->with('8.8.8.8')
            ->andReturn('dns.google');

        $repository
            ->shouldReceive('touch')
            ->once()
            ->with($this->link, '8.8.8.8')
            ->andReturn(true);

        $service = new ReverseDnsService(
            $repository,
            $resolver,
        );

        $service->update(
            $this->link,
            '8.8.8.8'
        );
    });


    it('inserts a new hostname when no database record exists', function () {
        $repository = Mockery::mock(ReverseDnsRepository::class);
        $resolver = Mockery::mock(ReverseDnsResolver::class);

        $repository
            ->shouldReceive('find')
            ->once()
            ->with($this->link, '8.8.8.8')
            ->andReturn(null);

        $resolver
            ->shouldReceive('resolve')
            ->once()
            ->with('8.8.8.8')
            ->andReturn('dns.google');

        $repository
            ->shouldReceive('insert')
            ->once()
            ->with(
                $this->link,
                '8.8.8.8',
                'dns.google'
            )
            ->andReturn(true);

        $service = new ReverseDnsService(
            $repository,
            $resolver,
        );

        $service->update(
            $this->link,
            '8.8.8.8'
        );
    });


    it('updates the hostname when DNS returns a different hostname', function () {
        $repository = Mockery::mock(ReverseDnsRepository::class);
        $resolver = Mockery::mock(ReverseDnsResolver::class);

        $repository
            ->shouldReceive('find')
            ->once()
            ->andReturn([
                'rdns' => 'old.example.com',
                'last_checked' => time() - TTL_REVERSE_DNS - 1,
            ]);

        $resolver
            ->shouldReceive('resolve')
            ->once()
            ->with('8.8.8.8')
            ->andReturn('dns.google');

        $repository
            ->shouldReceive('update')
            ->once()
            ->with(
                $this->link,
                '8.8.8.8',
                'dns.google'
            )
            ->andReturn(true);

        $service = new ReverseDnsService(
            $repository,
            $resolver,
        );

        $service->update(
            $this->link,
            '8.8.8.8'
        );
    });


    it('only touches the record when DNS returns the same hostname', function () {
        $repository = Mockery::mock(ReverseDnsRepository::class);
        $resolver = Mockery::mock(ReverseDnsResolver::class);

        $repository
            ->shouldReceive('find')
            ->once()
            ->andReturn([
                'rdns' => 'dns.google',
                'last_checked' => time() - TTL_REVERSE_DNS - 1,
            ]);

        $resolver
            ->shouldReceive('resolve')
            ->once()
            ->with('8.8.8.8')
            ->andReturn('dns.google');

        $repository
            ->shouldReceive('touch')
            ->once()
            ->with($this->link, '8.8.8.8')
            ->andReturn(true);

        $repository
            ->shouldNotReceive('insert');

        $repository
            ->shouldNotReceive('update');

        $service = new ReverseDnsService(
            $repository,
            $resolver,
        );

        $service->update(
            $this->link,
            '8.8.8.8'
        );
    });


    it('inserts NULL when DNS resolution fails and no record exists', function () {
        $repository = Mockery::mock(ReverseDnsRepository::class);
        $resolver = Mockery::mock(ReverseDnsResolver::class);

        $repository
            ->shouldReceive('find')
            ->once()
            ->andReturn(null);

        $resolver
            ->shouldReceive('resolve')
            ->once()
            ->with('192.0.2.1')
            ->andReturn(false);

        $repository
            ->shouldReceive('insert')
            ->once()
            ->with(
                $this->link,
                '192.0.2.1',
                null
            )
            ->andReturn(true);

        $service = new ReverseDnsService(
            $repository,
            $resolver,
        );

        $service->update(
            $this->link,
            '192.0.2.1'
        );
    });


    it('sets NULL when DNS resolution fails for an existing record', function () {
        $repository = Mockery::mock(ReverseDnsRepository::class);
        $resolver = Mockery::mock(ReverseDnsResolver::class);

        $repository
            ->shouldReceive('find')
            ->once()
            ->andReturn([
                'rdns' => 'old.example.com',
                'last_checked' => time() - TTL_REVERSE_DNS - 1,
            ]);

        $resolver
            ->shouldReceive('resolve')
            ->once()
            ->with('192.0.2.1')
            ->andReturn(false);

        $repository
            ->shouldReceive('update')
            ->once()
            ->with(
                $this->link,
                '192.0.2.1',
                null
            )
            ->andReturn(true);

        $service = new ReverseDnsService(
            $repository,
            $resolver,
        );

        $service->update(
            $this->link,
            '192.0.2.1'
        );
    });


    it('treats the IP itself as an invalid DNS result', function () {
        $repository = Mockery::mock(ReverseDnsRepository::class);
        $resolver = Mockery::mock(ReverseDnsResolver::class);

        $repository
            ->shouldReceive('find')
            ->once()
            ->andReturn(null);

        $resolver
            ->shouldReceive('resolve')
            ->once()
            ->with('192.0.2.1')
            ->andReturn('192.0.2.1');

        $repository
            ->shouldReceive('insert')
            ->once()
            ->with(
                $this->link,
                '192.0.2.1',
                null
            )
            ->andReturn(true);

        $service = new ReverseDnsService(
            $repository,
            $resolver,
        );

        $service->update(
            $this->link,
            '192.0.2.1'
        );
    });


    it('treats an invalid hostname as a failed DNS result', function () {
        $repository = Mockery::mock(ReverseDnsRepository::class);
        $resolver = Mockery::mock(ReverseDnsResolver::class);

        $repository
            ->shouldReceive('find')
            ->once()
            ->andReturn(null);

        $resolver
            ->shouldReceive('resolve')
            ->once()
            ->with('192.0.2.1')
            ->andReturn('not a valid hostname');

        $repository
            ->shouldReceive('insert')
            ->once()
            ->with(
                $this->link,
                '192.0.2.1',
                null
            )
            ->andReturn(true);

        $service = new ReverseDnsService(
            $repository,
            $resolver,
        );

        $service->update(
            $this->link,
            '192.0.2.1'
        );
    });
});
