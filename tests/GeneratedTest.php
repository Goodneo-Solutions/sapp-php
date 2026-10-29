<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Superroute\SApp\Generated\OpenApi;
use Superroute\SApp\Generated\PartnerApi;

/** The generated clients cover every operation of the specs (skipped outside the source repository). */
final class GeneratedTest extends TestCase
{
    private const SPECS = __DIR__ . '/../../specs';

    public function testEveryOperationOfTheSpecsHasAMethod(): void
    {
        if (!is_file(self::SPECS . '/operation-counts.json')) {
            self::markTestSkipped('the specs live in the source repository');
        }
        $counts = json_decode((string) file_get_contents(self::SPECS . '/operation-counts.json'), true);
        self::assertCount($counts['partner'], PartnerApi::operations());
        self::assertCount($counts['open'], OpenApi::operations());

        foreach (['partner-api.json' => PartnerApi::class, 'open-api.json' => OpenApi::class] as $file => $class) {
            $spec = json_decode((string) file_get_contents(self::SPECS . '/' . $file), true);
            $ids = [];
            foreach ($spec['paths'] as $path => $methods) {
                if ($class === PartnerApi::class && (str_starts_with($path, '/app/oauth') || $path === '/app/graphql')) {
                    continue;
                }
                foreach ($methods as $verb => $op) {
                    if (in_array($verb, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                        $ids[] = $op['operationId'];
                    }
                }
            }
            sort($ids);
            $have = array_keys($class::operations());
            sort($have);
            self::assertSame($ids, $have);
            foreach ($ids as $id) {
                self::assertTrue(method_exists($class, $id), "{$class}::{$id}");
            }
        }
        self::assertSame('key', PartnerApi::operations()['platformAppBootstrap']->auth);
        self::assertSame('token', PartnerApi::operations()['driverEarnings']->auth);
    }
}
