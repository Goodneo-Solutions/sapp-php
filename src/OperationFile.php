<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** A file sent with a multipart operation (photos, signatures). */
final class OperationFile
{
    public function __construct(
        public readonly string $field,
        public readonly string $content,
        public readonly string $filename,
        public readonly string $contentType = 'application/octet-stream',
    ) {
    }
}
