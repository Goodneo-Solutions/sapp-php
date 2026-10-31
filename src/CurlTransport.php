<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** The default transport when ext-curl is loaded. */
final class CurlTransport implements Transport
{
    public function __construct(private readonly int $timeoutSeconds = 30, private readonly int $connectTimeoutSeconds = 10)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportException('The request could not be prepared.');
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $received = [];
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$received): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $received[$name] = isset($received[$name]) ? $received[$name] . ', ' . trim($parts[1]) : trim($parts[1]);
                } elseif (str_starts_with($line, 'HTTP/')) {
                    $received = [];
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $answer = curl_exec($handle);
        if ($answer === false) {
            $error = curl_error($handle);
            throw new TransportException('The request could not reach Superroute. ' . $error);
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new Response($status, $received, (string) $answer);
    }
}
