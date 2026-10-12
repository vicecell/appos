<?php

declare(strict_types=1);

namespace ServerWatch\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ServerWatch\Repositories\TenantScoped;

/**
 * Architectural guard: every public method of a tenant-scoped repository must take the organization id as its
 * first parameter, so a query without tenant scope cannot be written by accident.
 */
final class RepositoryScopeTest extends TestCase
{
    /**
     * @return list<class-string>
     */
    private function scopedRepositories(): array
    {
        $found = [];
        foreach (glob(__DIR__ . '/../../app/Repositories/*.php') ?: [] as $file) {
            $class = 'ServerWatch\\Repositories\\' . basename($file, '.php');
            if (class_exists($class) && in_array(TenantScoped::class, class_implements($class) ?: [], true)) {
                $found[] = $class;
            }
        }
        return $found;
    }

    public function testThereAreTenantScopedRepositories(): void
    {
        $names = array_map(static fn (string $c): string => (new ReflectionClass($c))->getShortName(), $this->scopedRepositories());
        self::assertContains('ServerRepository', $names);
        self::assertContains('MemberRepository', $names);
    }

    public function testEveryPublicMethodTakesOrganizationIdFirst(): void
    {
        foreach ($this->scopedRepositories() as $class) {
            foreach ((new ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->isStatic()) {
                    continue;
                }
                $params = $method->getParameters();
                self::assertNotEmpty($params, "{$class}::{$method->getName()} has no parameters");
                self::assertSame(
                    'organizationId',
                    $params[0]->getName(),
                    "{$class}::{$method->getName()} must take organizationId first",
                );
                self::assertSame('int', (string) $params[0]->getType());
            }
        }
    }
}
