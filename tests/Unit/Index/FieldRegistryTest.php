<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Index;

use EsLite\Exception\ConfigurationException;
use EsLite\Index\FieldRegistry;
use EsLite\Support\Config;
use PHPUnit\Framework\TestCase;

final class FieldRegistryTest extends TestCase
{
    public function testMapsNamesToStableIds(): void
    {
        $registry = FieldRegistry::default();

        self::assertSame(1, $registry->id('title'));
        self::assertSame('body', $registry->name(3));
        self::assertSame(['title', 'tags', 'body'], $registry->names());
        self::assertSame(3, $registry->count());
    }

    public function testExposesFieldBoosts(): void
    {
        $registry = FieldRegistry::default();

        self::assertSame(3.0, $registry->boost('title'));
        self::assertSame(1.0, $registry->boostById(3));
        self::assertSame(1.0, $registry->boost('unknown'));
    }

    public function testReadsFieldsFromConfiguration(): void
    {
        $config = new Config(['app' => ['fields' => [
            'headline' => ['id' => 7, 'boost' => 4.5],
            'text' => ['id' => 8],
        ]]]);

        $registry = FieldRegistry::fromConfig($config);

        self::assertSame(7, $registry->id('headline'));
        self::assertSame(4.5, $registry->boost('headline'));
        self::assertSame(1.0, $registry->boost('text'));
    }

    public function testRejectsDuplicateIds(): void
    {
        $this->expectException(ConfigurationException::class);
        new FieldRegistry(['a' => ['id' => 1], 'b' => ['id' => 1]]);
    }

    public function testRejectsIdsOutsideTheStorableRange(): void
    {
        $this->expectException(ConfigurationException::class);
        new FieldRegistry(['a' => ['id' => 300]]);
    }

    public function testRejectsAnEmptyFieldSet(): void
    {
        $this->expectException(ConfigurationException::class);
        new FieldRegistry([]);
    }

    public function testRejectsUnknownFieldLookups(): void
    {
        $this->expectException(ConfigurationException::class);
        FieldRegistry::default()->id('category');
    }

    public function testRoundTripsThroughAnArray(): void
    {
        $registry = FieldRegistry::default();

        self::assertSame($registry->toArray(), (new FieldRegistry($registry->toArray()))->toArray());
    }
}
