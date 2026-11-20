<?php

declare(strict_types=1);

namespace Tests\Support;

use EzPhp\Contracts\RouterInterface;

/**
 * RouterInterface stub that records GET registrations, for provider tests.
 * A path given as `$rejects` is refused like the framework Router refuses a
 * duplicate route.
 */
final class MetricsRecordingRouter implements RouterInterface
{
    /** @var list<string> */
    public array $getPaths = [];

    /**
     * @param string|null $rejects A GET path to reject with "Duplicate route".
     */
    public function __construct(private readonly ?string $rejects = null)
    {
    }

    public function get(string $path, callable|array $handler): object
    {
        if ($path === $this->rejects) {
            throw new \InvalidArgumentException("Duplicate route: GET $path is already registered.");
        }

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
