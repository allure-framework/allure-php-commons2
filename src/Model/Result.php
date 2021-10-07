<?php

declare(strict_types=1);

namespace Qameta\Allure\Model;

abstract class Result implements ResultInterface
{
    use JsonSerializableTrait;

    private bool $muted = false;

    public function __construct(
        protected string $uuid,
    ) {
    }

    final public function getUuid(): string
    {
        return $this->uuid;
    }

    final public function getMuted(): bool
    {
        return $this->muted;
    }

    final public function setMuted(bool $muted = true): static
    {
        $this->muted = $muted;

        return $this;
    }

    /**
     * @return list<string>
     */
    protected function excludeFromSerialization(): array
    {
        return ['muted'];
    }
}
