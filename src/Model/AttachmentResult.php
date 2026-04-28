<?php

declare(strict_types=1);

namespace Qameta\Allure\Model;

class AttachmentResult extends Result
{
    protected ?string $name = null;

    protected ?string $source = null;

    protected ?string $type = null;

    protected ?string $fileExtension = null;

    public function getResultType(): ResultType
    {
        return ResultType::attachment();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getFileExtension(): ?string
    {
        return $this->fileExtension;
    }

    public function setFileExtension(?string $fileExtension): static
    {
        $this->fileExtension = $fileExtension;

        return $this;
    }

    protected function excludeFromSerialization(): array
    {
        return ['uuid', 'fileExtension', ...parent::excludeFromSerialization()];
    }

    public function getNestedResults(): array
    {
        return [];
    }
}
