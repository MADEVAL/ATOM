# HTTP Test Client

Fluent API for testing routes without manual Request construction.

## Usage

```php
use Atom\Test\HttpClient;

$client = new HttpClient($app);

// GET request
$client->get('/users/42')
    ->assertOk()
    ->assertJson(['id' => 42]);

// POST with body
$client->post('/users', ['name' => 'Alice'])
    ->assertCreated();

// PUT, PATCH, DELETE
$client->put('/users/1', ['name' => 'Bob'])->assertOk();
$client->delete('/users/1')->assertNoContent();

// Status assertions
$client->get('/nonexistent')->assertNotFound();
$client->get('/admin')->assertForbidden();
$client->get('/error')->assertServerError();

// Body content check
$client->get('/')->assertBodyContains('Welcome');

// Raw response
$response = $client->get('/api/data')->getResponse();
echo $response->getContent();
```

## Available assertions

| Method | Description |
|---|---|
| `assertOk()` | 200 |
| `assertCreated()` | 201 |
| `assertNoContent()` | 204 |
| `assertNotFound()` | 404 |
| `assertForbidden()` | 403 |
| `assertServerError()` | 500 |
| `assertStatus(int)` | Any code |
| `assertJson(array)` | Subset match |
| `assertBodyContains(string)` | Substring |
| `getResponse()` | Raw Response |

## Setup

Use unique cache dir to isolate tests:

```php
protected function setUp(): void
{
    $this->tmpCache = sys_get_temp_dir() . '/atom_test_' . uniqid();
    $this->app = new Application(new Config(cacheDir: $this->tmpCache));
}

protected function tearDown(): void
{
    // cleanup cache files
}
```
