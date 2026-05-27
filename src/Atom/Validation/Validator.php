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
    public function __construct(
        public string $message = 'Invalid email',
        public bool $unicode = true,
    ) {}
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
    public function __construct(
        public string $message = 'Must be an integer',
        public ?int $min = null,
        public ?int $max = null,
    ) {}
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

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Ip
{
    public function __construct(
        public string $message = 'Invalid IP address',
        public bool $onlyV4 = false,
        public bool $onlyV6 = false,
        public bool $noReserved = false,
        public bool $noPrivate = false,
        public bool $global = false,
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Uuid
{
    public function __construct(public string $message = 'Invalid UUID') {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Boolean
{
    public function __construct(public string $message = 'Must be a boolean') {}
}

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class Each
{
    /** @param class-string $class */
    public function __construct(public string $class, public string $message = 'Invalid items') {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Domain
{
    public function __construct(
        public string $message = 'Invalid domain',
        public bool $hostname = false,
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Mac
{
    public function __construct(public string $message = 'Invalid MAC address') {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class FloatVal
{
    public function __construct(
        public string $message = 'Must be a float',
        public ?float $min = null,
        public ?float $max = null,
    ) {}
}

final class Validator
{
    /** @return array<string,list<string>> empty = valid */
    public static function validate(object $dto): array
    {
        $errors = [];
        $ref = new ReflectionClass($dto);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $value = $prop->getValue($dto);
            $name  = $prop->getName();

            $isNullable = $prop->getAttributes(Nullable::class) !== [];
            if ($isNullable && $value === null) continue;

            foreach ($prop->getAttributes(Required::class) as $attr) {
                $r = $attr->newInstance();
                if ($value === null || $value === '' || $value === []) {
                    $errors[$name][] = $r->message;
                }
            }

            if ($value === null || $value === '' || $value === []) continue;

            foreach ($prop->getAttributes(Regex::class) as $attr) {
                $r = $attr->newInstance();
                try {
                    if (Pcre::match($r->pattern, (string) $value) === null) {
                        $errors[$name][] = $r->message;
                    }
                } catch (\RuntimeException) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Email::class) as $attr) {
                $r = $attr->newInstance();
                $flags = $r->unicode ? FILTER_FLAG_EMAIL_UNICODE : 0;
                if (filter_var((string) $value, FILTER_VALIDATE_EMAIL, $flags) === false) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Integer::class) as $attr) {
                $r = $attr->newInstance();
                $options = [];
                if ($r->min !== null) $options['min_range'] = $r->min;
                if ($r->max !== null) $options['max_range'] = $r->max;
                $opts = $options !== [] ? ['options' => $options] : 0;
                if (filter_var($value, FILTER_VALIDATE_INT, $opts) === false) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Min::class) as $attr) {
                $r = $attr->newInstance();
                $num = is_int($value) || is_float($value) ? $value : (is_string($value) && ctype_digit(ltrim($value, '+-')) ? (float) $value : null);
                if ($num === null ? strlen((string) $value) < $r->value : $num < $r->value) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Max::class) as $attr) {
                $r = $attr->newInstance();
                $num = is_int($value) || is_float($value) ? $value : (is_string($value) && ctype_digit(ltrim($value, '+-')) ? (float) $value : null);
                if ($num === null ? strlen((string) $value) > $r->value : $num > $r->value) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Between::class) as $attr) {
                $r = $attr->newInstance();
                $num = is_int($value) || is_float($value) ? $value : (is_string($value) && ctype_digit(ltrim($value, '+-')) ? (float) $value : null);
                if ($num === null ? strlen((string) $value) < $r->min || strlen((string) $value) > $r->max : $num < $r->min || $num > $r->max) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(In::class) as $attr) {
                $r = $attr->newInstance();
                if (!in_array($value, $r->values, true)) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Url::class) as $attr) {
                $r = $attr->newInstance();
                if (filter_var((string) $value, FILTER_VALIDATE_URL) === false) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Confirmed::class) as $attr) {
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

            foreach ($prop->getAttributes(Ip::class) as $attr) {
                $r = $attr->newInstance();
                $flags = 0;
                if ($r->onlyV4) $flags |= FILTER_FLAG_IPV4;
                if ($r->onlyV6) $flags |= FILTER_FLAG_IPV6;
                if ($r->noReserved) $flags |= FILTER_FLAG_NO_RES_RANGE;
                if ($r->noPrivate) $flags |= FILTER_FLAG_NO_PRIV_RANGE;
                if ($r->global) $flags |= FILTER_FLAG_GLOBAL_RANGE;
                if (filter_var((string) $value, FILTER_VALIDATE_IP, $flags) === false) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Uuid::class) as $attr) {
                $r = $attr->newInstance();
                if (Pcre::match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $value) === null) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Boolean::class) as $attr) {
                $r = $attr->newInstance();
                if (filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === null) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Domain::class) as $attr) {
                $r = $attr->newInstance();
                $flags = $r->hostname ? FILTER_FLAG_HOSTNAME : 0;
                if (filter_var((string) $value, FILTER_VALIDATE_DOMAIN, $flags) === false) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Mac::class) as $attr) {
                $r = $attr->newInstance();
                if (filter_var((string) $value, FILTER_VALIDATE_MAC) === false) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(FloatVal::class) as $attr) {
                $r = $attr->newInstance();
                $options = [];
                if ($r->min !== null) $options['min_range'] = $r->min;
                if ($r->max !== null) $options['max_range'] = $r->max;
                $opts = $options !== [] ? ['options' => $options] : 0;
                if (filter_var($value, FILTER_VALIDATE_FLOAT, $opts) === false) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Each::class) as $attr) {
                $r = $attr->newInstance();
                if (!is_array($value)) {
                    $errors[$name][] = $r->message;
                } else {
                    foreach ($value as $i => $item) {
                        if (!is_array($item) && !is_object($item)) {
                            $errors[$name][] = "{$r->message} at index {$i}";
                            continue;
                        }
                        $itemErrors = self::validate(is_array($item) ? self::arrayToDto($r->class, $item) : $item);
                        foreach ($itemErrors as $field => $msgs) {
                            foreach ($msgs as $msg) {
                                $errors[$name][] = "[{$i}].{$field}: {$msg}";
                            }
                        }
                    }
                }
            }
        }
        return $errors;
    }

    public static function arrayToDto(string $class, array $data): object
    {
        $ref = new \ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if ($ctor !== null && $ctor->getNumberOfRequiredParameters() > 0) {
            $dto = $ref->newInstanceWithoutConstructor();
        } else {
            $dto = $ref->newInstance();
        }
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $pname = $prop->getName();
            if (array_key_exists($pname, $data)) {
                $prop->setValue($dto, $data[$pname]);
            }
        }
        return $dto;
    }
}

