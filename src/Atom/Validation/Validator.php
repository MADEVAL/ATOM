<?php
declare(strict_types=1);
namespace Atom\Validation;

use Atom\Support\Regex as Pcre;
use Attribute;
use ReflectionClass;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Required
{
    public function __construct(public string $message = 'Required') {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Regex
{
    public function __construct(public string $pattern, public string $message = 'Invalid format') {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Email
{
    public function __construct(public string $message = 'Invalid email') {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Min
{
    public function __construct(public int $value, public string $message = 'Too short') {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Max
{
    public function __construct(public int $value, public string $message = 'Too long') {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Integer
{
    public function __construct(public string $message = 'Must be an integer') {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Between
{
    public function __construct(public int $min, public int $max, public string $message = 'Out of range') {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class In
{
    /** @param list<string|int> $values */
    public function __construct(public array $values, public string $message = 'Invalid value') {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Url
{
    public function __construct(public string $message = 'Invalid URL') {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Nullable
{
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Confirmed
{
    public function __construct(public string $message = 'Confirmation does not match') {}
}

final class Validator
{
    /** @return array<string,list<string>> empty = valid */
    public static function validate(object $dto): array
    {
        $errors = [];
        $ref = new ReflectionClass($dto);
        foreach ($ref->getProperties() as $prop) {
            $value = $prop->getValue($dto);
            $name  = $prop->getName();

            $isNullable = $prop->getAttributes(Nullable::class) !== [];
            if ($isNullable && $value === null) continue;

            foreach ($prop->getAttributes(Required::class) as $attr) {
                /** @var Required $r */
                $r = $attr->newInstance();
                if ($value === null || $value === '' || $value === []) {
                    $errors[$name][] = $r->message;
                }
            }

            if ($value === null || $value === '' || $value === []) continue;

            foreach ($prop->getAttributes(Regex::class) as $attr) {
                /** @var Regex $r */
                $r = $attr->newInstance();
                if (Pcre::match($r->pattern, (string) $value) === null) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Email::class) as $attr) {
                /** @var Email $r */
                $r = $attr->newInstance();
                if (Pcre::match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', (string) $value) === null) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Integer::class) as $attr) {
                /** @var Integer $r */
                $r = $attr->newInstance();
                if (Pcre::match('/^-?\d+$/', (string) $value) === null) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Min::class) as $attr) {
                /** @var Min $r */
                $r = $attr->newInstance();
                if (is_numeric($value) ? (float) $value < $r->value : strlen((string) $value) < $r->value) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Max::class) as $attr) {
                /** @var Max $r */
                $r = $attr->newInstance();
                if (is_numeric($value) ? (float) $value > $r->value : strlen((string) $value) > $r->value) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Between::class) as $attr) {
                /** @var Between $r */
                $r = $attr->newInstance();
                $num = is_numeric($value) ? (float) $value : strlen((string) $value);
                if ($num < $r->min || $num > $r->max) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(In::class) as $attr) {
                /** @var In $r */
                $r = $attr->newInstance();
                if (!in_array($value, $r->values, true)) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Url::class) as $attr) {
                /** @var Url $r */
                $r = $attr->newInstance();
                if (Pcre::match('~^https?://[^\s/$.?#].[^\s]*$~i', (string) $value) === null) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Confirmed::class) as $attr) {
                /** @var Confirmed $r */
                $r = $attr->newInstance();
                try {
                    $confirmProp = $ref->getProperty($name . '_confirmation');
                    $confirmVal  = $confirmProp->getValue($dto);
                    if ($value !== $confirmVal) {
                        $errors[$name][] = $r->message;
                    }
                } catch (\ReflectionException) {
                    $errors[$name][] = "Missing confirmation field: {$name}_confirmation";
                }
            }
        }
        return $errors;
    }
}

final class ValidationException extends \RuntimeException
{
    /** @param array<string,list<string>> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Validation failed');
    }
}
