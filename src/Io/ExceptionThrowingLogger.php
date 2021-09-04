<?php

declare(strict_types=1);

namespace Qameta\Allure\Io;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionClassConstant;
use RuntimeException;
use Stringable;
use Throwable;

use function array_flip;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function is_countable;
use function is_object;
use function is_string;
use function str_replace;

use const ARRAY_FILTER_USE_KEY;

final class ExceptionThrowingLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var array<string, int>
     */
    private array $logLevels;

    private LoggerInterface $delegate;

    public function __construct(
        private string $thresholdLevel = LogLevel::WARNING,
        ?LoggerInterface $delegate = null,
    ) {
        $this->delegate = $delegate ?? new NullLogger();
        $this->logLevels = $this->setupLogLevels();
    }

    /**
     * @return array<string, int>
     */
    private function setupLogLevels(): array
    {
        $logLevelsRef = new ReflectionClass(LogLevel::class);

        return array_flip(
            array_map(
                fn (mixed $value): string => (string) $value,
                array_values(
                    $logLevelsRef->getConstants(ReflectionClassConstant::IS_PUBLIC),
                ),
            ),
        );
    }

    public function log(mixed $level, $message, array $context = []): void
    {
        $this->delegate->log($level, $message, $context);
        if ($this->shouldThrowException($level)) {
            /** @var mixed $previous */
            $previous = $context['exception'] ?? null;
            throw new RuntimeException(
                /**
                 * @psalm-suppress RedundantConditionGivenDocblockType
                 * @psalm-suppress DocblockTypeContradiction
                 */
                $this->buildMessage(
                    is_string($message) ? $message : (string) $message,
                    $context,
                ),
                0,
                $previous instanceof Throwable ? $previous : null,
            );
        }
    }

    private function buildMessage(string $messageTemplate, array $context): string
    {
        $filteredContext = array_filter(
            $context,
            fn (string|int $contextKey): bool => $contextKey !== 'exception',
            ARRAY_FILTER_USE_KEY,
        );
        $placeholders = array_map(
            fn (string|int $contextKey): string => '{' . $contextKey . '}',
            array_keys($filteredContext),
        );
        $values = array_map(
            fn (mixed $value): string => match (true) {
                is_string($value) => $value,
                is_countable($value) => '<array(' . count($value) . ')>',
                is_object($value) && !($value instanceof Stringable) => '<object(' . $value::class . ')>',
                null === $value => '<null>',
                default => (string) $value,
            },
            $filteredContext,
        );

        return str_replace($placeholders, $values, $messageTemplate);
    }

    private function shouldThrowException(mixed $level): bool
    {
        if (!is_string($level)) {
            return false;
        }

        $currentLevel = $this->logLevels[$level] ?? null;
        $thresholdLevel = $this->logLevels[$this->thresholdLevel] ?? null;

        return isset($currentLevel, $thresholdLevel) && $currentLevel <= $thresholdLevel;
    }
}
