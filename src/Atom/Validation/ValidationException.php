<?php
declare(strict_types=1);
namespace Atom\Validation;

final class ValidationException extends \RuntimeException
{
    /** @param array<string,list<string>> $errors */
    public function __construct(public readonly array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
    }
}
