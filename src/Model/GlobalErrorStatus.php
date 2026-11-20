<?php

declare(strict_types=1);

namespace Qameta\Allure\Model;

use JsonSerializable;

final class GlobalErrorStatus extends AbstractEnum implements JsonSerializable
{
    public static function failed(): self
    {
        return self::create('failed');
    }

    public static function broken(): self
    {
        return self::create('broken');
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }
}
