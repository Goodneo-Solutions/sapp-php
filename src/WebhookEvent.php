<?php

declare(strict_types=1);

namespace Superroute\SApp;

/**
 * A verified webhook event. `type` is the Superroute event type without the CloudEvents `ca.superroute.` prefix;
 * `format` is `native`, `ce_binary` or `ce_structured`; `data` is the body, or a structured envelope's `data`;
 * `payload` the whole decoded body. JSON objects are decoded as associative arrays, so an empty object reads as [].
 */
final class WebhookEvent
{
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $type,
        public readonly ?string $time,
        public readonly ?string $subject,
        public readonly ?string $source,
        /** The sending business (CloudEvents `srbusiness`). */
        public readonly ?int $business,
        /** The delivery attempt (CloudEvents binary `ce-srattempt`). */
        public readonly ?int $attempt,
        public readonly string $format,
        public readonly mixed $data,
        public readonly mixed $payload,
    ) {
    }
}
