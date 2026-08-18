<?php

declare(strict_types=1);

namespace Modules\Tenant\Tests\Unit;

use Mockery;
use Modules\Tenant\Actions\Domains\GetDomainsArrayAction;
use Modules\Tenant\Models\Domain;
use Modules\Tenant\Tests\TestCase;
use PHPUnit\Framework\Assert;

uses(TestCase::class);

test('domain model can be instantiated', function (): void {
    $domain = new Domain;

    Assert::assertInstanceOf(Domain::class, $domain);
});

test('get rows method works correctly', function (): void {
    $mock = Mockery::mock(GetDomainsArrayAction::class);
    tenantMockExpectation($mock, 'execute')
        ->once()
        ->andReturn([
            ['id' => 1, 'name' => 'test-domain.com'],
            ['id' => 2, 'name' => 'example.org'],
        ]);
    app()->instance(GetDomainsArrayAction::class, $mock);

    $domain = new Domain;
    $rows = $domain->getRows();

    Assert::assertCount(2, $rows);
    Assert::assertSame('test-domain.com', $rows[0]['name']);
    Assert::assertSame('example.org', $rows[1]['name']);
});
