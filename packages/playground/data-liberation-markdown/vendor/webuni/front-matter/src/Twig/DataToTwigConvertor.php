<?php

/*
 * This is part of the webuni/front-matter package.
 *
 * (c) Martin Hasoň <martin.hason@gmail.com>
 * (c) Webuni s.r.o. <info@webuni.cz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webuni\FrontMatter\Twig;

use DateTimeInterface;

class DataToTwigConvertor
{
    /** @var callable */
    private $convertor;

    public function __invoke(array $data): string
    {
        $convertor = $this->convertor;

        return (string) $convertor($data);
    }

    protected function __construct(callable $convertor)
    {
        $this->convertor = $convertor;
    }

    /**
     * Data will not be written to the twig template
     *
     * @return self
     */
    public static function nothing(): self
    {
        return new self(function () {
            return '';
        });
    }

    /**
     * @psalm-suppress MixedAssignment
     * @param bool $force Define the value of the variable even if it is passed to the template
     * @return self
     */
    public static function vars(bool $force = true): self
    {
        return new self(function (array $data) use ($force) {
            $content = '';
            foreach ($data as $key => $value) {
                if (is_int($key)) {
                    //TODO add log
                    continue;
                }

                $value = ($force ? '' : "$key is defined ? $key : ") . self::valueToTwig($value);
                $content .= "{% set {$key} = {$value} %}\n";
            }

            return $content;
        });
    }

    /**
     * Converts data to one twig variable
     *
     * @param string $name  Variable name
     * @param bool   $force Define the value of the variable even if it is passed to the template
     * @return self
     */
    public static function var(string $name, bool $force = true): self
    {
        return new self(function (array $data) use ($name, $force) {
            $value = ($force ? '' : "$name is defined ? $name : ") . self::valueToTwig($data);

            return "{% set {$name} = {$value} %}\n";
        });
    }

    /**
     * @psalm-suppress MixedAssignment
     * @param mixed $value
     * @return string
     */
    protected static function valueToTwig($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return '(' . $value->getTimestamp() . "|date_modify('0sec'))";
        }

        if (is_array($value)) {
            $twig = '{';
            foreach ($value as $key => $val) {
                $twig .= "$key: ".static::valueToTwig($val).", ";
            }
            return $twig."}";
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
