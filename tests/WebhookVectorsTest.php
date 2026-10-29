<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Superroute\SApp\Webhooks;
use Superroute\SApp\WebhookVerificationException;

/** The shared webhook vectors (testdata/webhooks/vectors.json; a copy in tests/fixtures for the published package). */
final class WebhookVectorsTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/fixtures/vectors.json';
    private const SOURCE = __DIR__ . '/../../testdata/webhooks/vectors.json';

    /** @return array<string, array{0: array}> */
    public static function vectors(): array
    {
        $doc = json_decode((string) file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);
        $out = [];
        foreach ($doc['vectors'] as $v) {
            $out[$v['name']] = [$v];
        }

        return $out;
    }

    public function testTheFixtureIsTheSharedFile(): void
    {
        if (!is_file(self::SOURCE)) {
            self::markTestSkipped('the shared vectors live in the source repository');
        }
        self::assertSame(file_get_contents(self::SOURCE), file_get_contents(self::FIXTURE), 'copy testdata/webhooks/vectors.json to php/tests/fixtures/');
    }

    #[DataProvider('vectors')]
    public function testVector(array $v): void
    {
        $run = static fn () => Webhooks::verify($v['body'], $v['headers'], $v['secrets'], $v['scheme'], $v['tolerance'] ?? null, $v['now']);
        if ($v['expect']['ok']) {
            $event = $run();
            $expected = $v['expect']['event'];
            self::assertSame($expected['id'], $event->id);
            self::assertSame($expected['type'], $event->type);
            self::assertSame($expected['time'], $event->time);
            self::assertSame($expected['subject'], $event->subject);
            self::assertSame($expected['source'], $event->source);
            self::assertSame($expected['business'], $event->business);
            self::assertSame($expected['attempt'], $event->attempt);
            self::assertSame($expected['format'], $event->format);
            self::assertSame($expected['data'], $event->data);
            self::assertNotNull($event->payload);
        } else {
            try {
                $run();
                self::fail("expected {$v['expect']['error']}");
            } catch (WebhookVerificationException $e) {
                self::assertSame($v['expect']['error'], $e->errorCode);
                self::assertSame($v['expect']['error'], $e->getErrorCode());
            }
        }
    }

    public function testPsr7StyleHeaderArraysAreRead(): void
    {
        $v = self::vectors()['standard: whsec_ secret, native body'][0];
        $headers = array_map(static fn ($value) => [$value], $v['headers']);
        self::assertSame('evt_01J9', Webhooks::verify($v['body'], $headers, $v['secrets'][0], now: $v['now'])->id);
    }
}
