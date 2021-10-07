<?php

declare(strict_types=1);

namespace Qameta\Allure\Model;

use JsonSerializable;

interface ResultInterface extends JsonSerializable
{

    public function getUuid(): string;

    public function getResultType(): ResultType;

    public function getMuted(): bool;

    public function setMuted(bool $muted = true): static;

    /**
     * @return list<ResultInterface>
     */
    public function getNestedResults(): array;
}
