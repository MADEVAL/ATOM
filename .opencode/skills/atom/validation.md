# Validation

PCRE-powered attribute validation. Rules: `#[Required]`, `#[Email]`, `#[Regex('...')]`, `#[Min(N)]`, `#[Max(N)]`, `#[Integer]`, `#[Between(min,max)]`, `#[In([...])]`, `#[Url]`, `#[Nullable]`, `#[Confirmed]`.

## DTO definition

```php
use Atom\Validation\{Required, Email, Min, Max, Regex, Integer, Between, In, Url, Nullable, Confirmed};

final class CreateUser {
    #[Required]
    public string $name = '';

    #[Required] #[Email]
    public string $email = '';

    #[Min(8)] #[Max(128)]
    public string $password = '';

    #[Regex('/^[a-z0-9_]{3,20}$/')]
    public string $username = '';

    #[Integer]
    public string $age = '';

    #[Between(1, 120)]
    public int $ageStrict = 0;

    #[In(['admin', 'user', 'guest'])]
    public string $role = 'user';

    #[Url]
    public string $website = '';

    #[Confirmed]
    public string $password = '';
    public string $password_confirmation = '';

    #[Nullable] #[Email]
    public ?string $backupEmail = null;
}
```

## Manual validation

```php
$errors = Validator::validate($dto);
// returns: array<string, list<string>>
// empty array = valid
// ['name' => ['Required'], 'email' => ['Invalid email']]
```

## Request validation

Maps body fields to DTO properties, validates, throws on failure:

```php
try {
    $dto = $request->validate(CreateUser::class);
} catch (ValidationException $e) {
    Response::json($e->errors, StatusCode::BAD_REQUEST)->send();
}
```

## Custom messages

```php
#[Required('Name is mandatory')]
#[Email('Please enter a valid email')]
#[Min(8, 'Password must be at least 8 characters')]
#[Regex('/^[a-z]+$/', 'Only lowercase letters allowed')]
#[Between(5, 50, 'Must be between 5 and 50')]
#[In(['a','b'], 'Pick a or b')]
#[Url('Not a valid URL')]
#[Integer('Must be a whole number')]
#[Confirmed('Passwords do not match')]
```

## Attribute reference

| Attribute | Validates |
|---|---|
| `#[Required]` | Non-null, non-empty, non-[] |
| `#[Email]` | `user@domain.tld` via PCRE |
| `#[Regex('/pat/')]` | PCRE match |
| `#[Min(N)]` | min length (string) or min value (numeric) |
| `#[Max(N)]` | max length (string) or max value (numeric) |
| `#[Integer]` | Integer via regex `^-?\d+$` |
| `#[Between(min, max)]` | Numeric range or string length range |
| `#[In(['a','b'])]` | Value in list (strict comparison) |
| `#[Url]` | `http(s)://` prefix via PCRE |
| `#[Nullable]` | Skip validation when value is null |
| `#[Confirmed]` | Field must match `{field}_confirmation` |
