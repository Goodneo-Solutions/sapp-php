<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** The request never got an answer (DNS, connection, TLS, timeout). */
final class TransportException extends \RuntimeException
{
}
