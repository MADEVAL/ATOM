# HTTP Layer

## Request

Property hooks with PHP 8.5 syntax:

```php
$req->method   // GET|POST|PUT|PATCH|DELETE (supports _method spoofing)
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

### File uploads

```php
$file = $req->file('avatar');
if ($file->ok) {
    $file->move('/uploads/' . $file->name);
}
$file->size;   // bytes
$file->type;   // MIME
$file->ext;    // file extension via PCRE
$file->error;  // UPLOAD_ERR_*
$file->tmp;    // temp path
```

### JSON body auto-parse

When `Content-Type: application/json`, body is auto-decoded from `php://input`.

```php
// POST /api {"name": "Alice"} → $req->input('name') === 'Alice'
```

### Method spoofing

POST with `_method=PUT` → `$req->method === 'PUT'`. Works only from POST.

```html
<form method="POST" action="/users/42">
    <input type="hidden" name="_method" value="PUT">
</form>
```

### Bearer token

```php
// Authorization: Bearer abc123
$req->bearer  // 'abc123' — extracted via PCRE
```

## Response

### Factories

```php
Response::html('<h1>Hello</h1>')
Response::json(['key' => 'value'], pretty: true)
Response::json($data, StatusCode::CREATED)
Response::text('plain text')
Response::noContent()              // 204
Response::redirect('/login')       // 302
Response::redirect('/new', StatusCode::MOVED)  // 301
```

### Chaining

```php
(new Response('body', StatusCode::CREATED))
    ->withHeader('X-Custom', 'val')
    ->withStatus(StatusCode::NOT_FOUND)
    ->withCookie('session', $token, ttl: 7200, path: '/')
    ->withCache(3600)   // Cache-Control: public, max-age=3600
    ->send();
```

### Security

- Header injection blocked: `Regex::replace('#[\r\n]+#', ' ', $v)`
- Cookies default: `httponly: true, samesite: Lax`

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
$session = $app->container->make(Session::class);  // auto-started, singleton

$session->set('user_id', 42);
$session->get('user_id');           // 42
$session->has('user_id');           // true
$session->remove('user_id');
$session->flash('success', 'Saved!');  // next request only
$session->regenerate();                // rotate session ID
$token = $session->csrfToken();        // generates once, persists in session
$session->validateCsrf($token);        // constant-time comparison
```
