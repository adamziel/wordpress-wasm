<?php

declare(strict_types=1);

namespace Rowbot\Idna;

class DomainInfo
{
    /**
     * @var bool
     */
    protected $bidiDomain;

    /**
     * @var int
     */
    protected $errors;

    /**
     * @var bool
     */
    protected $validBidiDomain;

    /**
     * @var bool
     */
    protected $transitionalDifferent;

    public function __construct()
    {
        $this->bidiDomain = false;
        $this->errors = 0;
        $this->transitionalDifferent = false;
        $this->validBidiDomain = true;
    }

    public function addError(int $errors): void
    {
        $this->errors |= $errors;
    }

    public function getErrors(): int
    {
        return $this->errors;
    }

    /**
     * A Bidi domain name is a domain name containing at least one character with Bidi_Class R, AL, or AN.
     *
     * @see https://www.unicode.org/reports/tr46/#Notation
     * @see https://www.unicode.org/reports/tr9/#Bidirectional_Character_Types
     */
    public function isBidiDomain(): bool
    {
        return $this->bidiDomain;
    }

    public function isTransitionalDifferent(): bool
    {
        return $this->transitionalDifferent;
    }

    public function isValidBidiDomain(): bool
    {
        return $this->validBidiDomain;
    }

    public function setBidiDomain(): void
    {
        $this->bidiDomain = true;
    }

    public function setInvalidBidiDomain(): void
    {
        $this->validBidiDomain = false;
    }

    public function setTransitionalDifferent(): void
    {
        $this->transitionalDifferent = true;
    }
}
