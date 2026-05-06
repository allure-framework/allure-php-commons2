<?php

declare(strict_types=1);

namespace Qameta\Allure\Model;

use DateTimeImmutable;

final class GlobalAttachment extends AttachmentResult
{
    protected SerializableDate $timestamp;

    public function __construct(
        string $uuid,
        DateTimeImmutable $timestamp,
        ?string $name = null,
        ?string $source = null,
        ?string $type = null,
        ?string $fileExtension = null,
    ) {
        parent::__construct($uuid);

        $this
            ->setName($name)
            ->setSource($source)
            ->setType($type)
            ->setFileExtension($fileExtension);

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
