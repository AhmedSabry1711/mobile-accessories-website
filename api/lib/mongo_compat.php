<?php
/**
 * Mobi Boost — MongoDB value-type polyfills for the MySQL / PDO driver.
 *
 * The REST endpoints build small BSON value objects (`new MongoDB\BSON\UTCDateTime()`,
 * `new MongoDB\BSON\Regex(...)`, ids). On a host that has the real `mongodb`
 * extension those classes exist already; on Hostinger shared hosting they often
 * do not. This file defines minimal stand-ins with the same method surface so
 * that every endpoint runs unchanged.
 *
 * Only load this from the MySQL edition of `_bootstrap.php`. Each class is
 * guarded, so it is a no-op when the genuine extension is present.
 */
declare(strict_types=1);

namespace MongoDB\BSON {

    if (!class_exists('MongoDB\\BSON\\ObjectId', false)) {
        class ObjectId implements \JsonSerializable
        {
            private string $id;

            public function __construct(?string $id = null)
            {
                if ($id === null || $id === '') {
                    $this->id = bin2hex(random_bytes(12));
                    return;
                }
                $id = strtolower($id);
                if (!preg_match('/^[a-f0-9]{24}$/', $id)) {
                    throw new \InvalidArgumentException('Invalid ObjectId: ' . $id);
                }
                $this->id = $id;
            }

            public function __toString(): string
            {
                return $this->id;
            }

            public function jsonSerialize(): string
            {
                return $this->id;
            }
        }
    }

    if (!class_exists('MongoDB\\BSON\\UTCDateTime', false)) {
        class UTCDateTime implements \JsonSerializable
        {
            private int $milliseconds;

            /** @param int|float|string|\DateTimeInterface|null $milliseconds */
            public function __construct($milliseconds = null)
            {
                if ($milliseconds instanceof \DateTimeInterface) {
                    $this->milliseconds = (int)$milliseconds->format('Uv');
                } elseif (is_int($milliseconds) || is_float($milliseconds)) {
                    $this->milliseconds = (int)$milliseconds;
                } elseif (is_string($milliseconds) && is_numeric($milliseconds)) {
                    $this->milliseconds = (int)$milliseconds;
                } else {
                    $this->milliseconds = (int)round(microtime(true) * 1000);
                }
            }

            public function toDateTime(): \DateTimeImmutable
            {
                return (new \DateTimeImmutable('@' . intdiv($this->milliseconds, 1000)))
                    ->setTimezone(new \DateTimeZone('UTC'));
            }

            public function jsonSerialize(): string
            {
                return $this->toDateTime()->format(\DateTimeInterface::ATOM);
            }
        }
    }

    if (!class_exists('MongoDB\\BSON\\Regex', false)) {
        class Regex implements \JsonSerializable
        {
            private string $pattern;
            private string $flags;

            public function __construct(string $pattern, string $flags = '')
            {
                $this->pattern = $pattern;
                $this->flags = $flags;
            }

            public function getPattern(): string
            {
                return $this->pattern;
            }

            public function getFlags(): string
            {
                return $this->flags;
            }

            public function jsonSerialize(): string
            {
                return $this->pattern;
            }
        }
    }
}
