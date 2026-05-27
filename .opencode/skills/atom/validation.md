# Validation

18 attribute rules powered by PHP `filter_var` and PCRE. Rules: `#[Required]`, `#[Email]`, `#[Regex]`, `#[Min]`, `#[Max]`, `#[Integer]`, `#[Between]`, `#[In]`, `#[Url]`, `#[Nullable]`, `#[Confirmed]`, `#[Ip]`, `#[Domain]`, `#[Mac]`, `#[Float]`, `#[Boolean]`, `#[Uuid]`, `#[Each]`.

## DTO definition

```php
use Atom\Validation\{Required, Email, Min, Max, Regex, Integer, Between, In, Url, Nullable, Confirmed, Ip, Domain, Mac, FloatVal, Boolean, Uuid, Each};

final class CreateUser {
    #[Required]
    public string $name = '';

    #[Required] #[Email(unicode: true)]
    public string $email = '';

    #[Min(8)] #[Max(128)]
    public string $password = '';

    #[Regex('/^[a-z0-9_]{3,20}$/')]
    public string $username = '';

    #[Integer(min: 0, max: 150)]
    public int $ageStrict = 0;

    #[Between(1, 120)]
    public int $ageBetween = 0;

    #[In(['admin', 'user', 'guest'])]
    public string $role = 'user';

    #[Url]
    public string $website = '';

    #[Confirmed]
    public string $password = '';
    public string $password_confirmation = '';

    #[Nullable] #[Email]
    public ?string $backupEmail = null;

    #[Ip(noPrivate: true)]
    public string $publicIp = '';

    #[Domain(hostname: true)]
    public string $host = '';

    #[Mac]
    public string $mac = '';

    #[FloatVal(min: 0.0, max: 100.0)]
    public float $score = 0.0;

    #[Boolean]
    public bool $active = true;

    #[Uuid]
    public string $id = '';
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

Maps body fields to DTO properties, validates, throws on failure. Uses constructor when available (preserves defaults), falls back to `newInstanceWithoutConstructor` for required-param constructors.

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
#[Email('Invalid email', unicode: true)]
#[Integer('Must be a number', min: 0, max: 100)]
#[Regex('/^[a-z]+$/', 'Only lowercase letters allowed')]
#[Between(5, 50, 'Must be between 5 and 50')]
#[In(['a','b'], 'Pick a or b')]
#[Url('Not a valid URL')]
#[Ip('Invalid IP', noPrivate: true)]
#[Domain('Invalid domain', hostname: true)]
#[FloatVal('Must be a float', min: 0.0, max: 100.0)]
#[Confirmed('Passwords do not match')]
```

## Attribute reference

| Attribute | Validates |
|---|---|
| `#[Required]` | Non-null, non-empty, non-[] |
| `#[Email]` | `FILTER_VALIDATE_EMAIL` (RFC 822), `unicode` flag for IDN local part |
| `#[Regex('/pat/')]` | PCRE match, handles backtrack limit gracefully |
| `#[Min(N)]` | String length or numeric value ≥ N |
| `#[Max(N)]` | String length or numeric value ≤ N |
| `#[Integer]` | `FILTER_VALIDATE_INT`, optional `min`/`max` range |
| `#[Between(min, max)]` | Numeric range or string length range |
| `#[In(['a','b'])]` | Value in list (strict comparison) |
| `#[Url]` | `FILTER_VALIDATE_URL` (RFC 2396) |
| `#[Nullable]` | Skip validation when value is null |
| `#[Confirmed]` | Field must match `{field}_confirmation` |
| `#[Ip]` | `FILTER_VALIDATE_IP`, flags: `onlyV4`, `onlyV6`, `noReserved`, `noPrivate`, `global` |
| `#[Domain]` | `FILTER_VALIDATE_DOMAIN` (RFC 952/1034/1035/1123/2732/2181), `hostname` flag |
| `#[Mac]` | `FILTER_VALIDATE_MAC` |
| `#[Float]` | `FILTER_VALIDATE_FLOAT`, optional `min`/`max` range |
| `#[Boolean]` | `FILTER_VALIDATE_BOOL` — accepts 1/0/true/false/on/off/yes/no |
| `#[Uuid]` | `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx` format |
| `#[Each(Class)]` | Validates each array element as nested DTO |

## Nested array validation

```php
final class OrderItem {
    #[Required] public string $sku = '';
    #[Min(1)] public int $qty = 0;
}

final class CreateOrder {
    #[Required] public string $customer = '';
    #[Each(OrderItem::class)] public array $items = [];
}

$dto = new CreateOrder();
$dto->customer = 'John';
$dto->items = [
    ['sku' => 'A', 'qty' => 1],      // valid
    ['sku' => '', 'qty' => 0],       // errors: [1].sku, [1].qty
];
$errors = Validator::validate($dto);
```
