<?php
declare(strict_types=1);
namespace Atom\Tests\Validation;

use Atom\Validation\{Required, Regex, Email, Min, Max, Validator, ValidationException};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Validator::class)]
#[CoversClass(Required::class)]
#[CoversClass(Regex::class)]
#[CoversClass(Email::class)]
#[CoversClass(Min::class)]
#[CoversClass(Max::class)]
#[CoversClass(ValidationException::class)]
final class ValidatorTest extends TestCase
{
    #[Test]
    public function required_fails_for_null(): void
    {
        $dto = new class { #[Required] public ?string $name = null; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('name', $errors);
    }

    #[Test]
    public function required_fails_for_empty_string(): void
    {
        $dto = new class { #[Required] public string $name = ''; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('name', $errors);
    }

    #[Test]
    public function required_passes_for_non_empty(): void
    {
        $dto = new class { #[Required] public string $name = 'John'; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('name', $errors);
    }

    #[Test]
    public function regex_validates_pattern(): void
    {
        $dto = new class { #[Regex('#^\d+$#')] public string $code = 'abc'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('code', $errors);
    }

    #[Test]
    public function regex_passes_for_matching(): void
    {
        $dto = new class { #[Regex('#^\d+$#')] public string $code = '123'; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('code', $errors);
    }

    #[Test]
    public function regex_custom_message(): void
    {
        $dto = new class { #[Regex('#^[A-Z]+$#', 'Only uppercase letters')] public string $code = 'abc'; };
        $errors = Validator::validate($dto);
        $this->assertContains('Only uppercase letters', $errors['code']);
    }

    #[Test]
    public function email_validates_format(): void
    {
        $dto = new class { #[Email] public string $email = 'not-email'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('email', $errors);
    }

    #[Test]
    public function email_passes_for_valid(): void
    {
        $dto = new class { #[Email] public string $email = 'john@example.com'; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('email', $errors);
    }

    #[Test]
    public function min_validates_string_length(): void
    {
        $dto = new class { #[Min(3)] public string $name = 'ab'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('name', $errors);
    }

    #[Test]
    public function min_passes_for_long_enough(): void
    {
        $dto = new class { #[Min(3)] public string $name = 'John'; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('name', $errors);
    }

    #[Test]
    public function min_validates_numeric_value(): void
    {
        $dto = new class { #[Min(10)] public int $age = 5; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('age', $errors);
    }

    #[Test]
    public function max_validates_string_length(): void
    {
        $dto = new class { #[Max(5)] public string $code = 'too-long'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('code', $errors);
    }

    #[Test]
    public function max_passes_for_short_enough(): void
    {
        $dto = new class { #[Max(10)] public string $name = 'John'; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('name', $errors);
    }

    #[Test]
    public function max_validates_numeric_value(): void
    {
        $dto = new class { #[Max(100)] public int $age = 150; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('age', $errors);
    }

    #[Test]
    public function multiple_rules_on_one_field(): void
    {
        $dto = new class {
            #[Required]
            #[Email]
            public string $email = '';
        };
        $errors = Validator::validate($dto);
        $this->assertCount(1, $errors['email']); // Required stops Email check
    }

    #[Test]
    public function multiple_fields_with_errors(): void
    {
        $dto = new class {
            #[Required] public string $name = '';
            #[Email] public string $email = 'bad';
        };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    #[Test]
    public function validation_exception_stores_errors(): void
    {
        $ex = new ValidationException(['name' => ['Required']]);
        $this->assertSame(['name' => ['Required']], $ex->errors);
    }

    #[Test]
    public function validation_exception_message(): void
    {
        $ex = new ValidationException(['x' => ['err']]);
        $this->assertSame('Validation failed', $ex->getMessage());
    }

    #[Test]
    public function all_valid_returns_empty(): void
    {
        $dto = new class {
            #[Required] public string $name = 'John';
            #[Email] public string $email = 'john@example.com';
            #[Min(2)] public string $code = 'abc';
            #[Regex('#^[a-z]+$#')] public string $slug = 'hello';
        };
        $errors = Validator::validate($dto);
        $this->assertEmpty($errors);
    }

    #[Test]
    public function regex_skipped_when_value_empty(): void
    {
        $dto = new class { #[Regex('#^\d+$#')] public string $code = ''; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('code', $errors);
    }
}
