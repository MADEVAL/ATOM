<?php
declare(strict_types=1);
namespace Atom\Tests\Http;

use Atom\Http\Request;
use Atom\Validation\{Required, Email};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Request::class)]
final class RequestTest extends TestCase
{
    #[Test]
    public function construct_with_defaults(): void
    {
        $req = new Request();
        $this->assertSame('GET', $req->method);
        $this->assertSame('/', $req->path);
        $this->assertSame('localhost', $req->host);
    }

    #[Test]
    public function construct_with_custom_server(): void
    {
        $req = new Request(server: [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/api/users?id=1',
            'HTTP_HOST'      => 'example.com',
            'HTTPS'          => 'on',
            'REMOTE_ADDR'    => '192.168.1.1',
            'HTTP_ACCEPT'    => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $this->assertSame('POST', $req->method);
        $this->assertSame('/api/users', $req->path);
        $this->assertSame('/api/users?id=1', $req->uri);
        $this->assertSame('https', $req->scheme);
        $this->assertSame('example.com', $req->host);
        $this->assertSame('192.168.1.1', $req->ip);
        $this->assertSame('application/json', $req->accept);
        $this->assertTrue($req->isAjax);
    }

    #[Test]
    public function method_converts_to_uppercase(): void
    {
        $req = new Request(server: ['REQUEST_METHOD' => 'post']);
        $this->assertSame('POST', $req->method);
    }

    #[Test]
    public function path_uses_path_info_if_available(): void
    {
        $req = new Request(server: [
            'PATH_INFO'    => '/custom/path',
            'REQUEST_URI'  => '/other',
        ]);
        $this->assertSame('/custom/path', $req->path);
    }

    #[Test]
    public function path_falls_back_to_parsed_uri(): void
    {
        $req = new Request(server: ['REQUEST_URI' => '/foo/bar?x=1']);
        $this->assertSame('/foo/bar', $req->path);
    }

    #[Test]
    public function path_defaults_to_slash(): void
    {
        $req = new Request(server: []);
        $this->assertSame('/', $req->path);
    }

    #[Test]
    public function scheme_detects_https_on(): void
    {
        $req = new Request(server: ['HTTPS' => 'on']);
        $this->assertSame('https', $req->scheme);
    }

    #[Test]
    public function scheme_defaults_to_http(): void
    {
        $req = new Request(server: []);
        $this->assertSame('http', $req->scheme);
    }

    #[Test]
    public function host_defaults_to_localhost(): void
    {
        $req = new Request(server: []);
        $this->assertSame('localhost', $req->host);
    }

    #[Test]
    public function ip_defaults_to_localhost(): void
    {
        $req = new Request(server: []);
        $this->assertSame('127.0.0.1', $req->ip);
    }

    #[Test]
    public function is_ajax_detects_xmlhttprequest(): void
    {
        $req = new Request(server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $this->assertTrue($req->isAjax);
    }

    #[Test]
    public function is_ajax_false_without_header(): void
    {
        $req = new Request(server: []);
        $this->assertFalse($req->isAjax);
    }

    #[Test]
    public function accept_defaults_to_any(): void
    {
        $req = new Request(server: []);
        $this->assertSame('*/*', $req->accept);
    }

    #[Test]
    public function header_gets_from_headers_array(): void
    {
        $req = new Request(headers: ['content-type' => 'application/json']);
        $this->assertSame('application/json', $req->header('content-type'));
    }

    #[Test]
    public function header_falls_back_to_server(): void
    {
        $req = new Request(server: ['HTTP_ACCEPT' => 'text/html']);
        $this->assertSame('text/html', $req->header('accept'));
    }

    #[Test]
    public function header_returns_default_when_not_found(): void
    {
        $req = new Request();
        $this->assertSame('default-val', $req->header('x-custom', 'default-val'));
    }

    #[Test]
    public function header_returns_empty_string_by_default(): void
    {
        $req = new Request();
        $this->assertSame('', $req->header('x-nonexistent'));
    }

    #[Test]
    public function wants_json_returns_true_for_application_json(): void
    {
        $req = new Request(server: ['HTTP_ACCEPT' => 'application/json']);
        $this->assertTrue($req->wantsJson());
    }

    #[Test]
    public function wants_json_returns_true_for_vendor_json(): void
    {
        $req = new Request(server: ['HTTP_ACCEPT' => 'application/vnd.api+json']);
        $this->assertTrue($req->wantsJson());
    }

    #[Test]
    public function wants_json_returns_false_for_html(): void
    {
        $req = new Request(server: ['HTTP_ACCEPT' => 'text/html']);
        $this->assertFalse($req->wantsJson());
    }

    #[Test]
    public function input_from_body_first(): void
    {
        $req = new Request(body: ['name' => 'body-val'], query: ['name' => 'query-val']);
        $this->assertSame('body-val', $req->input('name'));
    }

    #[Test]
    public function input_falls_back_to_query(): void
    {
        $req = new Request(body: [], query: ['page' => '2']);
        $this->assertSame('2', $req->input('page'));
    }

    #[Test]
    public function input_returns_default(): void
    {
        $req = new Request();
        $this->assertNull($req->input('nonexistent'));
        $this->assertSame('fallback', $req->input('nonexistent', 'fallback'));
    }

    #[Test]
    public function construct_stores_all_properties(): void
    {
        $req = new Request(
            query: ['q' => 'search'],
            body: ['data' => 'value'],
            cookies: ['session' => 'abc'],
            files: ['avatar' => ['name' => 'pic.jpg']],
            server: ['HTTP_HOST' => 'test.com'],
        );

        $this->assertSame(['q' => 'search'], $req->query);
        $this->assertSame(['data' => 'value'], $req->body);
        $this->assertSame(['session' => 'abc'], $req->cookies);
        $this->assertSame(['avatar' => ['name' => 'pic.jpg']], $req->files);
    }

    #[Test]
    public function extract_headers_parses_http_prefix(): void
    {
        $req = new Request(server: [
            'HTTP_CONTENT_TYPE' => 'text/plain',
            'HTTP_X_CUSTOM'     => 'value',
            'SERVER_PROTOCOL'   => 'HTTP/1.1',
        ]);

        $this->assertSame('text/plain', $req->header('content-type'));
        $this->assertSame('value', $req->header('x-custom'));
    }

    #[Test]
    public function extract_headers_ignores_non_http_keys(): void
    {
        $req = new Request(server: [
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'DOCUMENT_ROOT'   => '/var/www',
        ]);
        $headers = $req->headers;
        $this->assertArrayNotHasKey('server-protocol', $headers);
        $this->assertArrayNotHasKey('document-root', $headers);
    }

    #[Test]
    public function capture_uses_globals(): void
    {
        $_GET['test'] = 'captured';
        $req = Request::capture();
        $this->assertSame('captured', $req->query['test']);
        unset($_GET['test']);
    }

    #[Test]
    public function capture_creates_usable_request(): void
    {
        $req = Request::capture();
        $this->assertInstanceOf(Request::class, $req);
        $this->assertIsString($req->method);
    }

    #[Test]
    public function bearer_token_extracted_from_header(): void
    {
        $req = new Request(headers: ['authorization' => 'Bearer abc123xyz']);
        $this->assertSame('abc123xyz', $req->bearer);
    }

    #[Test]
    public function bearer_token_case_insensitive(): void
    {
        $req = new Request(server: ['HTTP_AUTHORIZATION' => 'bearer TOKEN456']);
        $this->assertSame('TOKEN456', $req->bearer);
    }

    #[Test]
    public function bearer_token_empty_without_header(): void
    {
        $req = new Request();
        $this->assertSame('', $req->bearer);
    }

    #[Test]
    public function bearer_token_empty_for_basic_auth(): void
    {
        $req = new Request(server: ['HTTP_AUTHORIZATION' => 'Basic dXNlcjpwYXNz']);
        $this->assertSame('', $req->bearer);
    }

    #[Test]
    public function method_spoofing_via_method_field(): void
    {
        $req = new Request(
            body: ['_method' => 'PUT'],
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $this->assertSame('PUT', $req->method);
    }

    #[Test]
    public function method_spoofing_only_for_post(): void
    {
        $req = new Request(
            body: ['_method' => 'DELETE'],
            server: ['REQUEST_METHOD' => 'GET'],
        );
        $this->assertSame('GET', $req->method);
    }

    #[Test]
    public function json_body_not_parsed_when_body_provided(): void
    {
        $req = new Request(
            body: ['name' => 'explicit'],
            server: ['HTTP_CONTENT_TYPE' => 'application/json'],
        );
        $this->assertSame(['name' => 'explicit'], $req->body);
    }

    #[Test]
    public function empty_body_with_json_ctype_triggers_parse(): void
    {
        $req = new Request(
            body: [],
            server: ['HTTP_CONTENT_TYPE' => 'application/json; charset=utf-8'],
        );
        $this->assertIsArray($req->body);
    }

    #[Test]
    public function body_not_parsed_for_non_json_ctype(): void
    {
        $req = new Request(
            body: ['field' => 'value'],
            server: ['HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
        );
        $this->assertSame(['field' => 'value'], $req->body);
    }

    #[Test]
    public function empty_body_without_json_ctype_returns_empty(): void
    {
        $req = new Request(body: [], server: []);
        $this->assertSame([], $req->body);
    }

    #[Test]
    public function validate_passes_for_valid_data(): void
    {
        $req = new Request(body: ['name' => 'John', 'email' => 'john@test.com']);

        $ref = new \ReflectionClass(ValidatableUser::class);
        $dto = $req->validate(ValidatableUser::class);

        $this->assertSame('John', $dto->name);
        $this->assertSame('john@test.com', $dto->email);
    }

    #[Test]
    public function validate_throws_for_invalid_data(): void
    {
        $req = new Request(body: ['name' => '', 'email' => 'bad']);

        $this->expectException(\Atom\Validation\ValidationException::class);
        $req->validate(ValidatableUser::class);
    }

    #[Test]
    public function file_returns_uploaded_file(): void
    {
        $req = new Request(files: ['avatar' => [
            'name' => 'photo.jpg', 'type' => 'image/jpeg', 'size' => 1024,
            'tmp_name' => '/tmp/php123', 'error' => UPLOAD_ERR_OK,
        ]]);
        $file = $req->file('avatar');
        $this->assertTrue($file->ok);
        $this->assertSame('photo.jpg', $file->name);
    }

    #[Test]
    public function file_returns_empty_for_missing_key(): void
    {
        $req = new Request();
        $file = $req->file('missing');
        $this->assertFalse($file->ok);
    }

    #[Test]
    public function json_body_content_length_exceeded_returns_empty(): void
    {
        $req = new Request(
            body: [],
            server: [
                'HTTP_CONTENT_TYPE' => 'application/json',
                'HTTP_CONTENT_LENGTH' => '999999999',
            ],
        );
        $this->assertSame([], $req->body);
    }

    #[Test]
    public function json_body_invalid_json_returns_empty(): void
    {
        $req = new Request(
            body: [],
            server: ['HTTP_CONTENT_TYPE' => 'application/json'],
        );
        $this->assertSame([], $req->body);
    }

    #[Test]
    public function body_array_is_preserved_when_not_empty(): void
    {
        $req = new Request(
            body: ['a' => 1, 'b' => 2],
            server: ['HTTP_CONTENT_TYPE' => 'application/json'],
        );
        $this->assertSame(['a' => 1, 'b' => 2], $req->body);
    }

    #[Test]
    public function bearer_from_headers_array(): void
    {
        $req = new Request(headers: ['authorization' => 'Bearer xyz']);
        $this->assertSame('xyz', $req->bearer);
    }

    #[Test]
    public function explicit_empty_server_stays_empty(): void
    {
        $req = new Request(server: []);
        $this->assertSame([], $req->server);
    }

    #[Test]
    public function file_returns_empty_for_nested_upload_array(): void
    {
        $req = new Request(files: ['photos' => [
            'name' => ['a.jpg', 'b.jpg'],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'tmp_name' => ['/tmp/a', '/tmp/b'],
            'size' => [100, 200],
            'type' => ['image/jpeg', 'image/png'],
        ]]);
        $this->assertFalse($req->file('photos')->ok);
    }

    #[Test]
    public function file_missing_key_returns_empty(): void
    {
        $req = new Request(files: []);
        $this->assertFalse($req->file('nope')->ok);
    }

    #[Test]
    public function validate_uses_constructor_when_available(): void
    {
        $req = new Request(body: ['name' => 'test', 'email' => 'a@b.com']);
        $dto = $req->validate(ValidatableUser::class);
        $this->assertSame('test', $dto->name);
        $this->assertSame('a@b.com', $dto->email);
    }

    #[Test]
    public function method_spoofing_with_array_falls_back_to_post(): void
    {
        $req = new Request(
            body: ['_method' => ['PUT', 'DELETE']],
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $this->assertSame('POST', $req->method);
    }

    #[Test]
    public function method_spoofing_trims_whitespace(): void
    {
        $req = new Request(
            body: ['_method' => ' PUT '],
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $this->assertSame('PUT', $req->method);
    }
}

final class ValidatableUser
{
    #[Required] public string $name = '';
    #[Email] public string $email = '';
}
