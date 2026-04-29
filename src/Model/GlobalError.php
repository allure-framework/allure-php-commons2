<?php

declare(strict_types=1);

namespace Qameta\Allure\Model;

use DateTimeImmutable;

final class GlobalError extends StatusDetails
{
    protected SerializableDate $timestamp;

    public function __construct(
        DateTimeImmutable $timestamp,
        ?bool $known = null,
        ?bool $muted = null,
        ?bool $flaky = null,
        ?string $message = null,
        ?string $trace = null,
    ) {
        parent::__construct(
            known: $known,
            muted: $muted,
            flaky: $flaky,
            message: $message,
            trace: $trace,
        );

        $this->timestamp = new SerializableDate($timestamp);
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp->getDate();
    }

    public function setTimestamp(DateTimeImmutable $timestamp): self
    {
        $this->timestamp = new SerializableDate($timestamp);

        return $this;
    }
}
