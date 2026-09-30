<?php

declare(strict_types=1);

namespace Tests\Support;

use EzPhp\Contracts\RouterInterface;

/**
 * RouterInterface stub that records GET registrations, for provider tests.
 */
final class MetricsRecordingRouter implements RouterInterface
{
    /** @var list<string> */
    public array $getPaths = [];

    public function get(string $path, callable|array $handler): object
    {
        $this->getPaths[] = $path;

        return new \stdClass();
    }

    public function post(string $path, callable|array $handler): object
    {
        return new \stdClass();
    }

    public function put(string $path, callable|array $handler): object
    {
        return new \stdClass();
    }

    public function patch(string $path, callable|array $handler): object
    {
        return new \stdClass();
    }

    public function delete(string $path, callable|array $handler): object
    {
        return new \stdClass();
    }

    public function toCache(): array
    {
        return [];
    }
}
