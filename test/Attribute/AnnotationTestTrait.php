<?php

declare(strict_types=1);

namespace Qameta\Allure\Test\Attribute;

use Qameta\Allure\Attribute\AttributeInterface;
use Qameta\Allure\Attribute\AttributeReader;
use ReflectionMethod;

use function array_shift;

trait AnnotationTestTrait
{
    /**
     * @template T of AttributeInterface
     * @param class-string<T> $attributeClass
     * @param string          $methodName
     * @return T
     */
    protected function getAttributeInstance(string $attributeClass, string $methodName): AttributeInterface
    {
        $reader = new AttributeReader();
        $annotations = $reader->getMethodAnnotations(new ReflectionMethod($this, $methodName), $attributeClass);

        /** @var T */
        return array_shift($annotations);
    }
}
