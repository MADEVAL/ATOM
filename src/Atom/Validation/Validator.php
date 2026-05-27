<?php
declare(strict_types=1);
namespace Atom\Validation;

use Atom\Support\Regex as Pcre;
use ReflectionClass;

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
            $args = [];
            $resolvable = true;
            foreach ($ctor->getParameters() as $p) {
                if (array_key_exists($p->getName(), $data)) {
                    $args[] = $data[$p->getName()];
                } elseif ($p->isDefaultValueAvailable()) {
                    $args[] = $p->getDefaultValue();
                } else {
                    $resolvable = false;
                    break;
                }
            }
            $dto = $resolvable ? $ref->newInstanceArgs($args) : $ref->newInstanceWithoutConstructor();
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
