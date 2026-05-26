<?php
declare(strict_types=1);
namespace Atom\Validation;

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
                if (!preg_match($r->pattern, (string) $value)) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Email::class) as $attr) {
                /** @var Email $r */
                $r = $attr->newInstance();
                if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', (string) $value)) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Min::class) as $attr) {
                /** @var Min $r */
                $r = $attr->newInstance();
                if (strlen((string) $value) < $r->value || (is_numeric($value) && (float) $value < $r->value)) {
                    $errors[$name][] = $r->message;
                }
            }

            foreach ($prop->getAttributes(Max::class) as $attr) {
                /** @var Max $r */
                $r = $attr->newInstance();
                if (strlen((string) $value) > $r->value || (is_numeric($value) && (float) $value > $r->value)) {
                    $errors[$name][] = $r->message;
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
