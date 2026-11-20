<?php

declare(strict_types=1);

namespace Qameta\Allure\Model;

use JsonSerializable;

class StatusDetails implements JsonSerializable
{
    use JsonSerializableTrait;

    private ?string $actual = null;
    private ?string $expected = null;

    public function __construct(
        protected ?bool $known = null,
        protected ?bool $muted = null,
        protected ?bool $flaky = null,
        protected ?string $message = null,
        protected ?string $trace = null,
    ) {
    }

    public function isKnown(): ?bool
    {
        return $this->known;
    }

    public function makeKnown(?bool $known): static
    {
        $this->known = $known;

        return $this;
    }

    public function isMuted(): ?bool
    {
        return $this->muted;
    }

    public function makeMuted(?bool $muted): static
    {
        $this->muted = $muted;

        return $this;
    }

    public function isFlaky(): ?bool
    {
        return $this->flaky;
    }

    public function makeFlaky(?bool $flaky): static
    {
        $this->flaky = $flaky;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getTrace(): ?string
    {
        return $this->trace;
    }

    public function setTrace(?string $trace): static
    {
        $this->trace = $trace;

        return $this;
    }

    public function getActual(): ?string
    {
        return $this->actual;
    }

    public function setActual(?string $actual): static
    {
        $this->actual = $actual;

        return $this;
    }

    public function getExpected(): ?string
    {
        return $this->expected;
    }

    public function setExpected(?string $expected): static
    {
        $this->expected = $expected;

        return $this;
    }

    /**
     * @return list<string>
     */
    protected function excludeFromSerialization(): array
    {
        $excluded = [];
        if ($this->actual === null) {
            $excluded[] = 'actual';
        }
        if ($this->expected === null) {
            $excluded[] = 'expected';
        }

        return $excluded;
    }
}
