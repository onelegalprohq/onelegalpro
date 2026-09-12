<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Application;

use App\Modules\PlatformAdministration\Application\Queries\FindFirmRegistryEntry;
use App\Modules\PlatformAdministration\Application\Queries\FirmRegistryEntry;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmLifecycleState;
use PHPUnit\Framework\TestCase;

final class FirmRegistryQueryContractShapeTest extends TestCase
{
    public function test_query_has_one_exact_identifier_method_and_minimized_result(): void
    {
        $methods = (new \ReflectionClass(FindFirmRegistryEntry::class))->getMethods();

        $this->assertCount(1, $methods);
        $this->assertSame('find', $methods[0]->getName());
        $this->assertCount(1, $methods[0]->getParameters());
        $this->assertSame(FirmId::class, $methods[0]->getParameters()[0]->getType()?->getName());
        $this->assertSame('?'.FirmRegistryEntry::class, (string) $methods[0]->getReturnType());

        $entry = new \ReflectionClass(FirmRegistryEntry::class);
        $this->assertTrue($entry->isFinal());
        $this->assertTrue($entry->isReadOnly());
        $this->assertCount(2, $entry->getProperties());
        $this->assertSame(FirmId::class, $entry->getProperty('firmId')->getType()?->getName());
        $this->assertSame(FirmLifecycleState::class, $entry->getProperty('lifecycleState')->getType()?->getName());
    }
}
