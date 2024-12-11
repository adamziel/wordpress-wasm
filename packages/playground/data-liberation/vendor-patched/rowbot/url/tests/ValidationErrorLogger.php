<?php

declare(strict_types=1);

namespace Rowbot\URL\Tests;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

final class ValidationErrorLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @var mixed[]
     */
    private $messages;

    /**
     * @param string|\Stringable $message
     */
    public function log($level, $message, array $context = []): void
    {
        $this->messages[] = [$level, $message, $context];
    }
}
