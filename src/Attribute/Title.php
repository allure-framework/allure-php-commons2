<?php

declare(strict_types=1);

namespace Qameta\Allure\Attribute;

use Attribute;

/**
 * @deprecated Please use {@see DisplayName} attribute instead.`
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final class Title extends AbstractDisplayName
{
}
