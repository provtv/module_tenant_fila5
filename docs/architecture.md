---
title: Tenant Module Architecture
module: Tenant
type: architecture
tags: [design, patterns, isolation, multi-tenancy]
last_updated: 2026-08-04
---

# Tenant Module Architecture

## Core Principles

### 1. Complete Data Isolation
- **Database-level**: Each tenant routes through its own database connection
- **Schema-level**: Optional schema separation within same database
- **Query-level**: Automatic scoping via tenant context (middleware, resolver)

### 2. Multi-Domain Routing
- Single tenant → Multiple domains/subdomains
- Domain routing via Tenant middleware
- Automatic tenant resolution from request

### 3. Configuration Hierarchy
- Global defaults in `config/config.php`
- Per-tenant overrides in `TenantSetting` model
- Runtime resolution via `ConfigurationResolver`

---

## Directory Structure

```
Tenant/
├── app/
│   ├── Models/                   # 15+ core models
│   │   ├── Tenant.php            # Root multi-tenant entity
│   │   ├── Domain.php            # Domain/subdomain routing
│   │   ├── TenantSetting.php     # Per-tenant KV settings
│   │   ├── TenantSubscription.php # Subscription tracking
│   │   └── DatabaseConfig.php    # DB connection config
│   ├── Actions/                  # QueueableActions (business logic)
│   │   ├── Config/               # Configuration management
│   │   ├── Domains/              # Domain routing
│   │   ├── Models/               # Model operations
│   │   └── Modules/              # Cross-module integration
│   ├── Services/                 # Shared services
│   │   ├── TenantService.php     # Core isolation logic
│   │   ├── ConfigResolver.php    # Config lookup
│   │   └── DomainResolver.php    # Domain-to-tenant mapping
│   ├── Traits/                   # Reusable model traits
│   ├── Contracts/                # Service interfaces
│   ├── Filament/                 # Admin panel (Resources, Pages)
│   ├── Http/
│   │   ├── Middleware/           # TenantMiddleware, SetTenant
│   │   ├── Controllers/          # HTTP request handlers
│   │   └── Requests/             # Form validation requests
│   ├── Console/Commands/         # Artisan commands
│   ├── Providers/                # Service providers (4x)
│   └── Enums/                    # Type enumerations
├── database/
│   ├── migrations/               # 3 core migrations
│   ├── seeders/                  # 9 data seeders
│   └── factories/                # 8 model factories
├── config/
│   ├── config.php                # Module config (routes, nav, features)
│   ├── database.php              # DB strategy selection
│   └── metatag.php               # Per-tenant meta tags
├── routes/                       # Optional Tenant-specific routes
├── README.md                     # Module overview
├── tests/
│   ├── Feature/                  # Feature tests (isolation, routing)
│   ├── Unit/                     # Unit tests (services, models)
│   └── README.md                 # Test documentation
└── docs/
    ├── index.md                  # Documentation index
    ├── architecture.md           # This file
    └── configuration.md          # Config reference
```

---

## Core Models

### Tenant
Root entity for multi-tenancy.

```php
class Tenant extends XotBaseModel {
    protected $fillable = ['name', 'slug', 'domain', 'is_active', 'settings'];
    
    public function domains() { ... }
    public function settings() { ... }
    public function subscriptions() { ... }
}
```

**Relationships**:
- `domains()` — HasMany(Domain)
- `settings()` — HasMany(TenantSetting)
- `subscriptions()` — HasMany(TenantSubscription)

**Key Methods**:
- `currentTenant()` — Static: get active tenant from context
- `activate()` — Set as active tenant
- `deactivate()` — Disable tenant

### Domain
Domain/subdomain routing for tenant discovery.

```php
class Domain extends XotBaseModel {
    protected $fillable = ['tenant_id', 'domain', 'is_primary'];
    
    public function tenant() { ... }
}
```

**Routes**: domain → tenant_id via middleware

### TenantSetting
Key-value configuration storage per tenant.

```php
class TenantSetting extends XotBaseModel {
    protected $fillable = ['tenant_id', 'key', 'value'];
}
```

**Usage**: `$tenant->settings['key']`, resolved at runtime

### TenantSubscription
Subscription and plan tracking for billing/access control.

```php
class TenantSubscription extends XotBaseModel {
    protected $fillable = ['tenant_id', 'plan', 'status', 'expires_at'];
}
```

---

## Isolation Strategy

### Database Connection Resolution

```
Request → Domain Middleware
  ↓
Resolve Tenant ID from domain
  ↓
Set Database Connection (TenantService::setConnection($tenantId))
  ↓
All Models use tenant-specific connection automatically
```

**Files**:
- `Http/Middleware/TenantMiddleware.php` — Resolve tenant from domain
- `Services/TenantService.php` — Connection management
- `Services/DomainResolver.php` — Domain lookup

### Query Scoping Pattern

```php
// Automatic tenant scoping on models with HasTenant trait
public static function boot() {
    parent::boot();
    
    static::addGlobalScope(new TenantScope()); // Only query current tenant
}
```

**Models scoped**:
- All leaf module models via `HasTenant` trait
- Exception: `Tenant`, `Domain`, `TenantSetting` (system-level)

### Configuration Resolution

```
$config['key']
  ↓
Check TenantSetting (per-tenant override)
  ↓
Check config/config.php (global default)
  ↓
Return value
```

---

## Middleware Stack

### SetTenant Middleware
```php
use Modules\Tenant\Http\Middleware\SetTenant;

// In route group:
Route::middleware([SetTenant::class])->group(function () {
    // All routes here have $tenantId available
});
```

### TenantMiddleware (Domain Resolver)
```php
Route::middleware(TenantMiddleware::class)->group(function () {
    // Automatically resolve tenant from domain
    // Set connection, context, settings
});
```

---

## Service Providers

1. **TenantServiceProvider** — Register core services
2. **TenantAliasProvider** — Config aliases
3. **TenantAdminPanelProvider** — Filament integration
4. **TenantRouteServiceProvider** — Route binding

---

## Key Design Patterns

### 1. Actions Over Services
All business logic lives in `Actions/` namespace as `QueueableAction`.

```php
class ResolveTenantDomainAction {
    use QueueableAction;
    
    public function execute(string $domain): ?Tenant {
        return Domain::where('domain', $domain)
            ->first()
            ->tenant ?? null;
    }
}
```

### 2. Global Scope (Query Isolation)
```php
class TenantScope implements Scope {
    public function apply(Builder $builder, Model $model) {
        $builder->where('tenant_id', Tenant::currentTenant()->id);
    }
}
```

### 3. Trait-Based Extension
Models use `HasTenant` trait for automatic scoping:
```php
class SomeModel extends XotBaseModel {
    use HasTenant; // Auto-adds tenant_id foreign key, scoping, etc.
}
```

### 4. Middleware-First Routing
Tenant resolution happens at middleware layer (before controller):
```php
public function handle(Request $request, Closure $next) {
    $tenant = DomainResolver::resolve($request->getHost());
    app('tenant')->setActive($tenant);
    return $next($request);
}
```

---

## Testing Strategy

### Test Database Isolation
Use `.env.testing` with dedicated database:
```
DB_DATABASE=tenant_test
```

### Factory Fixtures
```php
use Modules\Tenant\Database\Factories\TenantFactory;

// Each test sets up isolated tenant
$tenant = TenantFactory::new()->create(['slug' => 'test-tenant']);
app('tenant')->setActive($tenant);
```

### Mocking Domain Resolution
```php
$this->mock(DomainResolver::class)
    ->shouldReceive('resolve')
    ->andReturn($tenant);
```

---

## Common Issues & Resolutions

| Issue | Cause | Solution |
|-------|-------|----------|
| Context leak (data from other tenant visible) | Global scope not applied | Verify `HasTenant` trait on model |
| Wrong database connection | TenantMiddleware not in stack | Add `TenantMiddleware` to route group |
| Settings not persisting | Using cache without invalidation | Call `ConfigResolver::clearCache()` after update |
| Domain routing fails | Domain not created for tenant | Create Domain record via Filament or factory |

---

## Integration Points

### With User Module
- `User` model has `tenant_id` foreign key
- Users scoped to their tenant
- Auth middleware checks tenant context

### With Xot Module
- Extends `XotBaseModel` for timestamped audit trail
- Uses Xot traits (SushiToJson, etc.)
- Service providers follow Xot patterns

### With Filament
- `TenantResource` for admin management
- `DomainResource` for domain configuration
- Custom pages for subscription management

---

## Performance Considerations

1. **Query Optimization**: Index `tenant_id` on all scoped models
2. **Connection Pooling**: Use separate connection pool per tenant database
3. **Settings Cache**: Cache `TenantSetting` lookups with invalidation
4. **Domain Cache**: Cache domain→tenant mapping with TTL

---

## Security Boundaries

1. **Never expose tenant context in URLs** — Use domain routing instead
2. **Validate tenant access** — Middleware ensures current user belongs to accessed tenant
3. **RLS Policies** — Database-level row security for additional safety
4. **Audit Trail** — All tenant-scoped operations logged via Xot traits

---

## Related Documentation

- [Configuration Reference](configuration.md)
- [Module README](../README.md)
- [Testing Guide](../tests/Feature/README.md)
- [Contributing Guidelines](./.github/CONTRIBUTING.md)
