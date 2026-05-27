<?php
declare(strict_types=1);
namespace Atom\Tests\Validation;

use Atom\Validation\{Required, Regex, Email, Min, Max, Integer, Between, In, Url, Nullable, Confirmed, Ip, Uuid, Boolean as VBoolean, Each, Domain, Mac, FloatVal, Validator, ValidationException};
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
#[CoversClass(Ip::class)]
#[CoversClass(Uuid::class)]
#[CoversClass(VBoolean::class)]
#[CoversClass(Each::class)]
#[CoversClass(Domain::class)]
#[CoversClass(Mac::class)]
#[CoversClass(FloatVal::class)]
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
    public function validator_skips_non_public_properties(): void
    {
        $dto = new class {
            #[Required] public string $name = 'ok';
            #[Required] private string $secret = 'hidden';
        };
        $errors = Validator::validate($dto);
        $this->assertSame([], $errors);
    }

    #[Test]
    public function min_max_reject_hex_strings_as_non_numeric(): void
    {
        $dto = new class {
            #[Min(100)] public string $v = '0x1A';
        };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('v', $errors);
    }

    #[Test]
    public function validator_class_is_covered(): void
    {
        $this->assertTrue(class_exists(Validator::class));
        $this->assertTrue(method_exists(Validator::class, 'validate'));
    }

    #[Test]
    public function ip_valid_passes(): void
    {
        $dto = new class { #[Ip] public string $ip = '192.168.1.1'; };
        $this->assertSame([], Validator::validate($dto));
    }

    #[Test]
    public function ip_v6_passes(): void
    {
        $dto = new class { #[Ip] public string $ip = '::1'; };
        $this->assertSame([], Validator::validate($dto));
    }

    #[Test]
    public function ip_invalid_fails(): void
    {
        $dto = new class { #[Ip] public string $ip = '999.999.999.999'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('ip', $errors);
    }

    #[Test]
    public function ip_v4_only_rejects_v6(): void
    {
        $dto = new class { #[Ip(onlyV4: true)] public string $ip = '::1'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('ip', $errors);
    }

    #[Test]
    public function ip_v6_only_rejects_v4(): void
    {
        $dto = new class { #[Ip(onlyV6: true)] public string $ip = '192.168.1.1'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('ip', $errors);
    }

    #[Test]
    public function ip_no_private_rejects_local(): void
    {
        $dto = new class { #[Ip(noPrivate: true)] public string $ip = '192.168.1.1'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('ip', $errors);
    }

    #[Test]
    public function uuid_valid_passes(): void
    {
        $dto = new class { #[Uuid] public string $id = '550e8400-e29b-41d4-a716-446655440000'; };
        $this->assertSame([], Validator::validate($dto));
    }

    #[Test]
    public function uuid_invalid_fails(): void
    {
        $dto = new class { #[Uuid] public string $id = 'not-a-uuid'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('id', $errors);
    }

    #[Test]
    public function boolean_true_passes(): void
    {
        foreach (['1', 'true', 1, true, 'on', 'yes'] as $v) {
            $dto = new class { #[VBoolean] public mixed $flag = null; };
            $dto->flag = $v;
            $this->assertSame([], Validator::validate($dto));
        }
    }

    #[Test]
    public function boolean_false_values_pass(): void
    {
        foreach (['0', 'false', 0, false, 'off', 'no', ''] as $v) {
            $dto = new class { #[VBoolean] public mixed $flag = null; };
            $dto->flag = $v;
            $this->assertSame([], Validator::validate($dto));
        }
    }

    #[Test]
    public function boolean_invalid_fails(): void
    {
        $dto = new class { #[VBoolean] public string $flag = 'maybe'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('flag', $errors);
    }

    #[Test]
    public function email_with_unicode_passes(): void
    {
        $dto = new class { #[Email] public string $email = 'üsër@example.com'; };
        $this->assertSame([], Validator::validate($dto));
    }

    #[Test]
    public function integer_with_min_max_range(): void
    {
        $dto = new class { #[Integer(min: 10, max: 100)] public int $v = 5; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('v', $errors);
    }

    #[Test]
    public function integer_within_range_passes(): void
    {
        $dto = new class { #[Integer(min: 10, max: 100)] public int $v = 50; };
        $this->assertSame([], Validator::validate($dto));
    }

    #[Test]
    public function domain_valid_passes(): void
    {
        $dto = new class { #[Domain] public string $d = 'example.com'; };
        $this->assertSame([], Validator::validate($dto));
    }

    #[Test]
    public function domain_invalid_fails(): void
    {
        $dto = new class { #[Domain] public string $d = 'example..com'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('d', $errors);
    }

    #[Test]
    public function domain_hostname_rejects_non_hostname(): void
    {
        $dto = new class { #[Domain(hostname: true)] public string $d = '-bad-.com'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('d', $errors);
    }

    #[Test]
    public function mac_valid_passes(): void
    {
        $dto = new class { #[Mac] public string $m = '00:1B:44:11:3A:B7'; };
        $this->assertSame([], Validator::validate($dto));
    }

    #[Test]
    public function mac_invalid_fails(): void
    {
        $dto = new class { #[Mac] public string $m = 'not-mac'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('m', $errors);
    }

    #[Test]
    public function float_valid_passes(): void
    {
        $dto = new class { #[FloatVal] public string $f = '3.14'; };
        $this->assertSame([], Validator::validate($dto));
    }

    #[Test]
    public function float_invalid_fails(): void
    {
        $dto = new class { #[FloatVal] public string $f = 'abc'; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('f', $errors);
    }

    #[Test]
    public function float_with_min_max_range(): void
    {
        $dto = new class { #[FloatVal(min: 1.0, max: 10.0)] public float $v = 0.5; };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('v', $errors);
    }

    #[Test]
    public function each_validates_array_of_dtos(): void
    {
        $dto = new class {
            #[Each(SomeItem::class)] public array $items = [];
        };
        $dto->items = [
            ['name' => 'a', 'count' => 5],
            ['name' => 'b', 'count' => -1],
        ];
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('items', $errors);
    }

    #[Test]
    public function each_passes_for_valid_items(): void
    {
        $dto = new class {
            #[Each(SomeItem::class)] public array $items = [];
        };
        $dto->items = [
            ['name' => 'a', 'count' => 5],
            ['name' => 'b', 'count' => 10],
        ];
        $this->assertSame([], Validator::validate($dto));
    }

    #[Test]
    public function min_max_accept_decimal_numeric_strings(): void
    {
        $dto = new class {
            #[Min(10)] public string $v = '25';
        };
        $errors = Validator::validate($dto);
        $this->assertSame([], $errors);
    }
}

final class SomeItem
{
    #[Required] public string $name = '';
    #[Min(0)] public int $count = 0;
}
