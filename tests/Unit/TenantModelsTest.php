<?php

declare(strict_types=1);

namespace Modules\Tenant\Tests\Unit;

use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Tests\TestCase;
use Modules\User\Database\Factories\UserFactory;
use Modules\User\Models\User;
use PHPUnit\Framework\Assert;
use Webmozart\Assert\Assert as WebmozartAssert;

uses(TestCase::class);

it('can create a tenant', function (): void {
    $tenant = createTenant([
        'name' => 'Test Company',
        'domain' => 'test.company.com',
        'database' => 'tenant_test_db',
    ]);

    Assert::assertInstanceOf(Tenant::class, $tenant);
    Assert::assertSame('Test Company', $tenant->name);
    Assert::assertSame('test.company.com', $tenant->domain);
    Assert::assertSame('tenant_test_db', $tenant->database);
});

it('can create a tenant with settings', function (): void {
    $tenant = createTenant([
        'name' => 'Settings Tenant',
        'domain' => 'settings.example.com',
        'settings' => ['locale' => 'it', 'timezone' => 'Europe/Rome'],
    ]);

    Assert::assertIsArray($tenant->settings);
    Assert::assertSame('it', $tenant->settings['locale'] ?? null);
});

it('exposes users relationship', function (): void {
    $tenant = createTenant([
        'name' => 'User Tenant',
        'domain' => 'user.example.com',
    ]);

    /** @var UserFactory $userFactory */
    $userFactory = User::factory();
    $user = $userFactory->create([
        'name' => 'Tenant User',
        'email' => 'user@tenant.example.com',
    ]);
    WebmozartAssert::isInstanceOf($user, User::class);

    $tenant->users()->save($user);

    Assert::assertTrue($tenant->users()->whereKey($user->id)->exists());
});

it('can create multiple users for a tenant', function (): void {
    $tenant = createTenant([
        'name' => 'Multi User Tenant',
        'domain' => 'multi.example.com',
    ]);

    /** @var UserFactory $userFactory */
    $userFactory = User::factory();
    $users = $userFactory->count(3)->create();
    foreach ($users->all() as $user) {
        $tenant->users()->save($user);
    }

    Assert::assertSame(3, $tenant->users()->count());
});

it('reports active state via isActive', function (): void {
    $active = createTenant(['is_active' => true]);
    $inactive = createTenant(['is_active' => false]);

    Assert::assertTrue($active->isActive());
    Assert::assertFalse($inactive->isActive());
});
