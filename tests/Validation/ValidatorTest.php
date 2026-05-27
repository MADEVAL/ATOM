<?php
declare(strict_types=1);
namespace Atom\Tests\Validation;

use Atom\Validation\{Required, Regex, Email, Min, Max, Integer, Between, In, Url, Nullable, Confirmed, Validator, ValidationException};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Validator::class)]
#[CoversClass(Required::class)]
#[CoversClass(Regex::class)]
#[CoversClass(Email::class)]
#[CoversClass(Min::class)]
#[CoversClass(Max::class)]
#[CoversClass(Integer::class)]
#[CoversClass(Between::class)]
#[CoversClass(In::class)]
#[CoversClass(Url::class)]
#[CoversClass(Nullable::class)]
#[CoversClass(Confirmed::class)]
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
        $this->assertCount(1, $errors['email']);
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

    // --- New attributes ---

    #[Test]
    public function integer_fails_for_non_integer(): void
    {
        $dto = new class { #[Integer] public string $age = 'abc'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('age', $errors);
    }

    #[Test]
    public function integer_passes_for_numeric_string(): void
    {
        $dto = new class { #[Integer] public string $count = '42'; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('count', $errors);
    }

    #[Test]
    public function integer_passes_for_int(): void
    {
        $dto = new class { #[Integer] public int $id = 7; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('id', $errors);
    }

    #[Test]
    public function integer_fails_for_float_string(): void
    {
        $dto = new class { #[Integer] public string $val = '3.14'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('val', $errors);
    }

    #[Test]
    public function between_fails_below_min(): void
    {
        $dto = new class { #[Between(5, 10)] public int $val = 3; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('val', $errors);
    }

    #[Test]
    public function between_fails_above_max(): void
    {
        $dto = new class { #[Between(1, 5)] public int $val = 10; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('val', $errors);
    }

    #[Test]
    public function between_passes_in_range(): void
    {
        $dto = new class { #[Between(1, 10)] public int $val = 5; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('val', $errors);
    }

    #[Test]
    public function between_passes_at_boundary(): void
    {
        $dto = new class { #[Between(3, 7)] public int $val = 3; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('val', $errors);
    }

    #[Test]
    public function in_fails_for_value_not_in_list(): void
    {
        $dto = new class { #[In(['a','b','c'])] public string $letter = 'z'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('letter', $errors);
    }

    #[Test]
    public function in_passes_for_value_in_list(): void
    {
        $dto = new class { #[In(['admin','user'])] public string $role = 'admin'; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('role', $errors);
    }

    #[Test]
    public function in_passes_for_int_in_list(): void
    {
        $dto = new class { #[In([1,2,3])] public int $num = 2; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('num', $errors);
    }

    #[Test]
    public function url_fails_for_invalid(): void
    {
        $dto = new class { #[Url] public string $link = 'not-a-url'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('link', $errors);
    }

    #[Test]
    public function url_passes_for_http(): void
    {
        $dto = new class { #[Url] public string $link = 'http://example.com'; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('link', $errors);
    }

    #[Test]
    public function url_passes_for_https(): void
    {
        $dto = new class { #[Url] public string $link = 'https://example.com/path?q=1'; };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('link', $errors);
    }

    #[Test]
    public function nullable_skips_validation_when_null(): void
    {
        $dto = new class {
            #[Nullable]
            #[Email]
            public ?string $email = null;
        };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('email', $errors);
    }

    #[Test]
    public function nullable_validates_when_not_null(): void
    {
        $dto = new class {
            #[Nullable]
            #[Email]
            public ?string $email = 'bad';
        };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('email', $errors);
    }

    #[Test]
    public function nullable_with_required_allows_null(): void
    {
        // Nullable + Required with null = Nullable wins, skip all validation
        $dto = new class {
            #[Required]
            #[Nullable]
            public ?string $name = null;
        };
        $errors = Validator::validate($dto);
        $this->assertArrayNotHasKey('name', $errors);
    }

    #[Test]
    public function confirmed_fails_when_mismatch(): void
    {
        $dto = new class {
            #[Confirmed]
            public string $password = 'secret';
            public string $password_confirmation = 'different';
        };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('password', $errors);
    }

    #[Test]
    public function confirmed_with_missing_confirmation_field_reports_error(): void
    {
        $dto = new class {
            #[Confirmed]
            public string $password = 'secret';
        };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('Missing confirmation field', $errors['password'][0]);
    }

    #[Test]
    public function validator_class_is_covered(): void
    {
        $this->assertTrue(class_exists(Validator::class));
        $this->assertTrue(method_exists(Validator::class, 'validate'));
    }
}
