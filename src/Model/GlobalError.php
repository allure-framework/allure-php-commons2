<?php

declare(strict_types=1);

namespace Qameta\Allure\Model;

use DateTimeImmutable;

final class GlobalError extends StatusDetails
{
    protected SerializableDate $timestamp;

    protected ?Status $status;

    public function __construct(
        DateTimeImmutable $timestamp,
        ?bool $known = null,
        ?bool $muted = null,
        ?bool $flaky = null,
        ?string $message = null,
        ?string $trace = null,
        ?Status $status = null,
    ) {
        parent::__construct(
            known: $known,
            muted: $muted,
            flaky: $flaky,
            message: $message,
            trace: $trace,
        );

        $this->timestamp = new SerializableDate($timestamp);
        $this->status = $status;
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

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(?Status $status): self
    {
        $this->status = $status;

        return $this;
    }
}
