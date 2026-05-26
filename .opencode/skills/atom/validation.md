# Validation

PCRE-powered attribute validation. Rules: `#[Required]`, `#[Email]`, `#[Regex('...')]`, `#[Min(N)]`, `#[Max(N)]`.

## DTO definition

```php
use Atom\Validation\{Required, Email, Min, Max, Regex};

final class CreateUser {
    #[Required]
    public string $name = '';

    #[Required] #[Email]
    public string $email = '';

    #[Min(8)]
    public string $password = '';

    #[Regex('/^[a-z0-9_]{3,20}$/')]
    public string $username = '';
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
```
