<?php

declare(strict_types=1);

namespace Qameta\Allure\Model\Exception;

use DomainException;
use Throwable;

final class InvalidGlobalErrorStatusException extends DomainException
{
    public function __construct(private string $status, ?Throwable $previous = null)
    {
        parent::__construct(
            "Global error status must be failed or broken (got: $this->status)",
            0,
            $previous,
        );
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
