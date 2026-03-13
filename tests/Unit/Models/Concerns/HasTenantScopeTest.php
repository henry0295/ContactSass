<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Concerns;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for HasTenantScope trait
 * 
 * Tests the tenant scoping functionality that ensures multi-tenant data isolation.
 * Validates Requirements 5.10 from the ContactSass Critical Improvements spec.
 */
final class HasTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test_models table for testing
        $this->createTestModelsTable();
    }

    protected function tearDown(): void
    {
        // Clear tenant context after each test
        app()->forgetInstance('current_tenant_id');
        
        parent::tearDown();
    }

    /**
     * Create the test_models table for testing
     */
    private function createTestModelsTable(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('test_models', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
        });
    }

    /**
     * Test that global scope filters by tenant_id when context is set
     * 
     * Validates that when a tenant context is set in the application container,
     * all queries automatically filter to only return records for that tenant.
     */
    public function test_global_scope_filters_by_tenant_id_when_context_is_set(): void
    {
        // Arrange: Set tenant context
        $tenantId = '550e8400-e29b-41d4-a716-446655440000';
        app()->instance('current_tenant_id', $tenantId);

        // Create test records with different tenant IDs
        $this->createTestModel($tenantId, 'Tenant 1 Record');
        $this->createTestModel('660e8400-e29b-41d4-a716-446655440001', 'Tenant 2 Record');
        $this->createTestModel($tenantId, 'Tenant 1 Another Record');

        // Act: Query without explicit tenant filter
        $results = TestModel::all();

        // Assert: Only records for the current tenant are returned
        $this->assertCount(2, $results);
        $this->assertTrue($results->every(fn($record) => $record->tenant_id === $tenantId));
        $this->assertEquals('Tenant 1 Record', $results->first()->name);
    }

    /**
     * Test that scopeForTenant() method filters correctly
     * 
     * Validates that the scopeForTenant() method can be used to explicitly
     * filter queries to a specific tenant, even when bypassing the global scope.
     */
    public function test_scope_for_tenant_method_filters_correctly(): void
    {
        // Arrange: Create test records with different tenant IDs
        $tenant1Id = '550e8400-e29b-41d4-a716-446655440000';
        $tenant2Id = '660e8400-e29b-41d4-a716-446655440001';
        
        $this->createTestModel($tenant1Id, 'Tenant 1 Record');
        $this->createTestModel($tenant2Id, 'Tenant 2 Record');
        $this->createTestModel($tenant1Id, 'Tenant 1 Another');

        // Act: Use scopeForTenant to filter (without global scope)
        $results = TestModel::withoutGlobalScope('tenant')
            ->forTenant($tenant1Id)
            ->get();

        // Assert: Only records for the specified tenant are returned
        $this->assertCount(2, $results);
        $this->assertTrue($results->every(fn($record) => $record->tenant_id === $tenant1Id));
    }

    /**
     * Test that scopeForTenant() can be chained with other query methods
     * 
     * Validates that the scopeForTenant() method returns a Builder instance
     * that can be chained with additional query constraints.
     */
    public function test_scope_for_tenant_can_be_chained(): void
    {
        // Arrange
        $tenantId = '550e8400-e29b-41d4-a716-446655440000';
        
        $this->createTestModel($tenantId, 'Alpha');
        $this->createTestModel($tenantId, 'Beta');
        $this->createTestModel('660e8400-e29b-41d4-a716-446655440001', 'Gamma');

        // Act: Chain scopeForTenant with where clause
        $results = TestModel::withoutGlobalScope('tenant')
            ->forTenant($tenantId)
            ->where('name', 'Alpha')
            ->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('Alpha', $results->first()->name);
        $this->assertEquals($tenantId, $results->first()->tenant_id);
    }

    /**
     * Test behavior when no tenant context is set
     * 
     * Validates that when no tenant context is set in the application container,
     * queries return all records without tenant filtering. This is important for
     * system-level operations and admin access.
     */
    public function test_no_filtering_when_no_tenant_context_is_set(): void
    {
        // Arrange: Ensure no tenant context is set
        app()->forgetInstance('current_tenant_id');

        // Create test records with different tenant IDs
        $this->createTestModel('550e8400-e29b-41d4-a716-446655440000', 'Tenant 1');
        $this->createTestModel('660e8400-e29b-41d4-a716-446655440001', 'Tenant 2');
        $this->createTestModel('770e8400-e29b-41d4-a716-446655440002', 'Tenant 3');

        // Act: Query without tenant context
        $results = TestModel::all();

        // Assert: All records are returned (no filtering applied)
        $this->assertCount(3, $results);
    }

    /**
     * Test that global scope can be explicitly removed
     * 
     * Validates that the global tenant scope can be bypassed when needed
     * using withoutGlobalScope(), allowing access to all tenant data.
     */
    public function test_global_scope_can_be_removed(): void
    {
        // Arrange: Set tenant context
        $tenantId = '550e8400-e29b-41d4-a716-446655440000';
        app()->instance('current_tenant_id', $tenantId);

        $this->createTestModel($tenantId, 'Tenant 1');
        $this->createTestModel('660e8400-e29b-41d4-a716-446655440001', 'Tenant 2');

        // Act: Query without the global scope
        $results = TestModel::withoutGlobalScope('tenant')->get();

        // Assert: All records are returned
        $this->assertCount(2, $results);
    }

    /**
     * Test that scopeForTenant returns a Builder instance
     * 
     * Validates that the scopeForTenant() method returns a proper Builder
     * instance for method chaining.
     */
    public function test_scope_for_tenant_returns_builder(): void
    {
        // Act
        $query = TestModel::withoutGlobalScope('tenant')
            ->forTenant('550e8400-e29b-41d4-a716-446655440000');

        // Assert
        $this->assertInstanceOf(Builder::class, $query);
    }

    /**
     * Test that multiple tenants can be queried independently
     * 
     * Validates that changing the tenant context properly isolates data
     * between different tenants.
     */
    public function test_multiple_tenants_are_isolated(): void
    {
        // Arrange: Create records for two tenants
        $tenant1Id = '550e8400-e29b-41d4-a716-446655440000';
        $tenant2Id = '660e8400-e29b-41d4-a716-446655440001';
        
        $this->createTestModel($tenant1Id, 'Tenant 1 Record A');
        $this->createTestModel($tenant1Id, 'Tenant 1 Record B');
        $this->createTestModel($tenant2Id, 'Tenant 2 Record A');

        // Act & Assert: Query for tenant 1
        app()->instance('current_tenant_id', $tenant1Id);
        $tenant1Results = TestModel::all();
        $this->assertCount(2, $tenant1Results);
        $this->assertTrue($tenant1Results->every(fn($r) => $r->tenant_id === $tenant1Id));

        // Act & Assert: Query for tenant 2
        app()->instance('current_tenant_id', $tenant2Id);
        $tenant2Results = TestModel::all();
        $this->assertCount(1, $tenant2Results);
        $this->assertTrue($tenant2Results->every(fn($r) => $r->tenant_id === $tenant2Id));
    }

    /**
     * Helper method to create a test model record
     */
    private function createTestModel(string $tenantId, string $name): void
    {
        $this->app['db']->connection()->table('test_models')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenantId,
            'name' => $name,
        ]);
    }
}

/**
 * Test model for HasTenantScope trait testing
 * 
 * This is a minimal model used exclusively for testing the HasTenantScope trait.
 * It includes only the necessary configuration to test tenant scoping behavior.
 */
class TestModel extends Model
{
    use HasTenantScope;

    protected $table = 'test_models';
    protected $fillable = ['tenant_id', 'name'];
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';
}
