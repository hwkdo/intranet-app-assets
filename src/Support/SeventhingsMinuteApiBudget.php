<?php

namespace Hwkdo\IntranetAppAssets\Support;

/**
 * Begrenzt HTTP-Aufrufe gegen die Seventhings-Customer-API auf max. N Requests
 * innerhalb eines gleitenden 60-Sekunden-Fensters (z. B. 60/min).
 */
final class SeventhingsMinuteApiBudget
{
    /** @var list<float> */
    private array $timestamps = [];

    public function __construct(
        private readonly int $maxPerMinute = 60,
    ) {
        if ($this->maxPerMinute < 1) {
            throw new \InvalidArgumentException('maxPerMinute muss mindestens 1 sein.');
        }
    }

    /**
     * Wartet bei Bedarf und registriert einen API-Aufruf.
     */
    public function acquire(): void
    {
        $now = microtime(true);
        $this->trimOlderThan($now, 60.0);

        while (count($this->timestamps) >= $this->maxPerMinute) {
            $oldest = min($this->timestamps);
            $waitSeconds = 60.0 - ($now - $oldest) + 0.05;
            if ($waitSeconds > 0) {
                usleep((int) round($waitSeconds * 1_000_000));
            }
            $now = microtime(true);
            $this->trimOlderThan($now, 60.0);
        }

        $this->timestamps[] = microtime(true);
    }

    private function trimOlderThan(float $now, float $windowSeconds): void
    {
        $this->timestamps = array_values(array_filter(
            $this->timestamps,
            static fn (float $t): bool => ($now - $t) < $windowSeconds
        ));
    }
}
