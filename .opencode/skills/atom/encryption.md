# Encryption

AES-256-GCM encryption with key derivation.

## Usage

```php
use Atom\Support\Encrypt;

$payload = Encrypt::encrypt('sensitive data', $app->config->get('APP_KEY'));
$plain   = Encrypt::decrypt($payload, $app->config->get('APP_KEY'));
```

Each encryption produces a different ciphertext (random IV). Tampered or wrong-key payloads throw `RuntimeException`.

## Use cases

- Signed cookies / remember-me tokens
- API key storage
- Encrypted configuration values
