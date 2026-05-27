# HTTP Layer

## Request

Property hooks with PHP 8.5 syntax:

```php
$req->method   // GET|POST|PUT|PATCH|DELETE (supports _method spoofing, trims whitespace)
$req->path     // /api/users
$req->uri      // /api/users?id=1
$req->scheme   // http|https
$req->host     // example.com
$req->ip       // 127.0.0.1
$req->isAjax   // true|false
$req->accept   // application/json
$req->bearer   // Bearer token from Authorization header

$req->header('Content-Type', 'default')
$req->input('key', 'default')
$req->wantsJson()
$req->file('avatar')   // → UploadedFile
```

Constructor accepts explicit arrays — empty `server: []` stays empty (no global fallback).

### File uploads

```php
$file = $req->file('avatar');
if ($file->ok) {
    $file->move('/uploads', $file->name);
}
$file->size;   // bytes
$file->type;   // MIME
$file->ext;    // file extension via PCRE
$file->error;  // UPLOAD_ERR_*
$file->tmp;    // temp path
```

`move($root, $relativePath = '')` always resolves the destination inside the allowed upload root. If `$relativePath` is omitted, the original upload basename is used. Nested upload arrays (`<input name="photos[]" multiple>`) return `UploadedFile::empty()`.

### JSON body auto-parse

When `Content-Type: application/json`, body is auto-decoded from `php://input`. Detection supports both standard `CONTENT_TYPE` and `HTTP_CONTENT_TYPE` server keys. Uses `ini_parse_quantity(ini_get('post_max_size'))` for content-length check.

```php
// POST /api {"name": "Alice"} → $req->input('name') === 'Alice'
```

### Method spoofing

POST with `_method=PUT` → `$req->method === 'PUT'`. Works only from POST. Whitespace trimmed. Array values fall back to POST.

```html
<form method="POST" action="/users/42">
    <input type="hidden" name="_method" value="PUT">
</form>
```

### Bearer token

```php
// Authorization: Bearer abc123
$req->bearer  // 'abc123' - extracted via PCRE
```

Multiline tokens rejected (regex `.+` doesn't match across newlines).

## Response

### Factories

```php
Response::html('<h1>Hello</h1>')
Response::json(['key' => 'value'], pretty: true)
Response::json($data, StatusCode::CREATED)
Response::text('plain text')
Response::noContent()              // 204
Response::redirect('/login')       // 302
Response::redirect('/new', StatusCode::PERMANENT_REDIRECT)  // 308
```

Dangerous redirect protocols (`javascript:`, `data:`, `vbscript:`) are blocked → redirected to `/`.

### Chaining

```php
(new Response('body', StatusCode::CREATED))
    ->withHeader('X-Custom', 'val')
    ->withStatus(StatusCode::NOT_FOUND)
    ->withCookie('session', $token, 7200)          // int ttl
    ->withCookie('session', $token, ['ttl' => 7200, 'path' => '/admin']) // array options
    ->withCache(3600)   // Cache-Control: public, max-age=3600
    ->send();
```

### Security

- Header injection: `\r\n` stripped from both key and value
- Cookies default: `httponly: true, samesite: Lax` (invalid values fall back to Lax)
- `name`/`value` in options array cannot override cookie identity
- `send(?bool $isHttps)` parameter for testable cookie `secure` flag

## Controllers

Return string → auto-wrapped to HTML. Return `Response` for full control:

```php
class UserController {
    public function show(string $id, Request $request): Response {
        return Response::json(findUser($id));
    }
    public function __invoke(): string {
        return 'Hello World';
    }
}
```

## Session

```php
// Lazy singleton – no session_start() until first use
$session = $app->container->make(Session::class);

// With custom cookie params
$session = new Session(['cookie_secure' => true, 'cookie_samesite' => 'Strict']);

// Standard operations
$session->set('user_id', 42);
$session->get('user_id');           // 42
$session->has('user_id');           // true
$session->remove('user_id');
$session->flash('success', 'Saved!');  // next request only
$session->regenerate();                // rotate session ID

// Global CSRF – rotated after successful validation
$token = $session->csrfToken();
$session->validateCsrf($token);

// Per-form CSRF — independent tokens for each form
$loginToken = $session->csrfToken('login');
$session->validateCsrf($loginToken, 'login');
```
