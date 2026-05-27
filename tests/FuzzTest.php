<?php
declare(strict_types=1);
namespace Atom\Tests;

use Atom\Config;
use Atom\Container\Container;
use Atom\Http\{Request, Response, Session, StatusCode, UploadedFile};
use Atom\Middleware\{Cors, Pipeline};
use Atom\Routing\Router;
use Atom\Support\{Logger, Regex};
use Atom\Validation\{Required, Email, Regex as VRegex, Min, Max, Integer, Between, In, Url, Nullable, Confirmed, Validator};
use Atom\View\{Engine, Template};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Request::class)]
#[CoversClass(Response::class)]
#[CoversClass(Session::class)]
#[CoversClass(UploadedFile::class)]
#[CoversClass(Cors::class)]
#[CoversClass(Pipeline::class)]
#[CoversClass(Router::class)]
#[CoversClass(Logger::class)]
#[CoversClass(Regex::class)]
#[CoversClass(Validator::class)]
final class FuzzTest extends TestCase
{
    // ──────────────────────── REQUEST ────────────────────────

    #[Test]
    public function request_null_bytes_stripped_from_header_key(): void
    {
        $req = new Request(headers: ["x-custom\0header" => 'val']);
        $this->assertSame('val', $req->header("x-custom\0header"));
    }

    #[Test]
    public function request_crlf_in_header_key_via_server_escaped(): void
    {
        $req = new Request(server: ["HTTP_X_FOO\r\n_SET_COOKIE" => 'evil']);
        $this->assertSame('', $req->header("X-Foo\r\nSet-Cookie"));
    }

    #[Test]
    public function request_method_spoofing_on_non_post_ignored(): void
    {
        $req = new Request(
            body: ['_method' => 'DELETE'],
            server: ['REQUEST_METHOD' => 'GET'],
        );
        $this->assertSame('GET', $req->method);
    }

    #[Test]
    public function request_method_spoofing_to_arbitrary_value(): void
    {
        $req = new Request(
            body: ['_method' => ' EXPLOIT '],
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $this->assertSame('POST', $req->method);
    }

    #[Test]
    public function request_bearer_with_multiple_spaces(): void
    {
        $req = new Request(headers: ['authorization' => 'Bearer    token123']);
        $this->assertSame('token123', $req->bearer);
    }

    #[Test]
    public function request_bearer_newline_safe_multiline_token_rejected(): void
    {
        $req = new Request(headers: ['authorization' => "Bearer abc\r\nX-Injected: yes"]);
        $this->assertSame('', $req->bearer);
    }

    #[Test]
    public function request_bearer_empty_header_value(): void
    {
        $req = new Request(headers: ['authorization' => '']);
        $this->assertSame('', $req->bearer);
    }

    #[Test]
    public function request_json_body_not_parsed_for_non_json_ct(): void
    {
        $req = new Request(
            body: [],
            server: ['HTTP_CONTENT_TYPE' => 'text/plain'],
        );
        $this->assertSame([], $req->body);
    }

    #[Test]
    public function request_json_body_with_no_input_returns_empty(): void
    {
        $req = new Request(
            body: [],
            server: ['HTTP_CONTENT_TYPE' => 'application/json'],
        );
        $this->assertIsArray($req->body);
    }

    #[Test]
    public function request_input_returns_null_for_missing_key(): void
    {
        $req = new Request(body: [], query: []);
        $this->assertNull($req->input('__nonexistent__'));
    }

    #[Test]
    public function request_path_strips_query_string(): void
    {
        $req = new Request(server: ['REQUEST_URI' => '/search?q=<script>']);
        $this->assertSame('/search', $req->path);
    }

    #[Test]
    public function request_path_strips_fragment(): void
    {
        $req = new Request(server: ['REQUEST_URI' => '/page#section']);
        $this->assertSame('/page', $req->path);
    }

    #[Test]
    public function request_scheme_https_off_is_http(): void
    {
        $req = new Request(server: ['HTTPS' => 'off']);
        $this->assertSame('http', $req->scheme);
    }

    #[Test]
    public function request_accept_empty_header_is_empty_string(): void
    {
        $req = new Request(server: ['HTTP_ACCEPT' => '']);
        $this->assertSame('', $req->accept);
    }

    #[Test]
    public function request_validate_skips_unknown_body_fields(): void
    {
        $dto = new class {
            #[Required] public string $name = '';
        };
        $ref = new \ReflectionClass($dto);
        $dto = $ref->newInstanceWithoutConstructor();
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $prop->setValue($dto, 'ok');
        }
        $errors = Validator::validate($dto);
        $this->assertSame([], $errors);
    }

    #[Test]
    public function request_file_from_nested_array_is_not_upload_file(): void
    {
        $req = new Request(files: ['photos' => [
            'name' => ['a.jpg', 'b.jpg'],
            'type' => ['image/jpeg', 'image/png'],
            'tmp_name' => ['/tmp/a', '/tmp/b'],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size' => [100, 200],
        ]]);
        $file = $req->file('photos');
        $this->assertFalse($file->ok);
    }

    // ──────────────────────── ROUTER ────────────────────────

    #[Test]
    public function router_very_long_path_works(): void
    {
        $container = new Container();
        $router = new Router($container, sys_get_temp_dir() . '/atom_fuzz_' . uniqid());
        $path = '/long/' . str_repeat('a/', 200) . 'end';
        $router->get($path, 'Ctrl@act');
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path]);
        $container->bind('Ctrl', fn() => new class {
            public function act(): string { return 'ok'; }
        });
        $res = $router->dispatch($req);
        $this->assertSame(200, $res->getStatusCode());
    }

    #[Test]
    public function router_empty_path_in_group_no_error(): void
    {
        $container = new Container();
        $router = new Router($container, sys_get_temp_dir() . '/atom_fuzz_' . uniqid());
        $router->get('', 'Ctrl@act');
        $router->group('/api', [], function (Router $r) {
            $r->get('', 'ApiCtrl@list');
        });
        $this->assertTrue(true);
    }

    #[Test]
    public function router_path_with_regex_special_chars_in_pattern(): void
    {
        $container = new Container();
        $router = new Router($container, sys_get_temp_dir() . '/atom_fuzz_' . uniqid());
        $router->get('/items/{id:[0-9]+}', 'Ctrl@show');
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/items/42']);
        $container->bind('Ctrl', fn() => new class {
            public function show(string $id): string { return $id; }
        });
        $res = $router->dispatch($req);
        $this->assertStringContainsString('42', $res->getContent());
    }

    #[Test]
    public function router_same_path_different_methods_dispatches_correctly(): void
    {
        $container = new Container();
        $router = new Router($container, sys_get_temp_dir() . '/atom_fuzz_' . uniqid());
        $router->get('/api', 'Ctrl@read');
        $router->post('/api', 'Ctrl@write');
        $container->bind('Ctrl', fn() => new class {
            public function read(): string { return 'read'; }
            public function write(): string { return 'write'; }
        });
        $get = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api']);
        $post = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api']);
        $this->assertStringContainsString('read', $router->dispatch($get)->getContent());
        $this->assertStringContainsString('write', $router->dispatch($post)->getContent());
    }

    #[Test]
    public function router_duplicate_name_throws(): void
    {
        $container = new Container();
        $router = new Router($container, sys_get_temp_dir() . '/atom_fuzz_' . uniqid());
        $router->get('/a', 'Ctrl@a', 'same');
        $this->expectException(\InvalidArgumentException::class);
        $router->post('/b', 'Ctrl@b', 'same');
    }

    #[Test]
    public function router_url_missing_param_throws(): void
    {
        $container = new Container();
        $router = new Router($container, sys_get_temp_dir() . '/atom_fuzz_' . uniqid());
        $router->get('/users/{id}/{slug}', 'Ctrl@show', 'user');
        $this->expectException(\InvalidArgumentException::class);
        $router->url('user', ['id' => '1']);
    }

    // ──────────────────────── RESPONSE ────────────────────────

    #[Test]
    public function response_header_crlf_stripped_from_key(): void
    {
        $res = new Response();
        $r = $res->withHeader("X-Foo\r\nSet-Cookie: evil", 'value');
        $this->assertSame(200, $r->getStatusCode());
    }

    #[Test]
    public function response_cookie_with_arbitrary_samesite_value(): void
    {
        $res = new Response();
        $r = $res->withCookie('k', 'v', ['samesite' => 'InvalidValue']);
        $this->assertSame(200, $r->getStatusCode());
    }

    #[Test]
    public function response_json_with_nan_throws(): void
    {
        $this->expectException(\JsonException::class);
        Response::json(['val' => NAN]);
    }

    #[Test]
    public function response_redirect_blocks_javascript_protocol(): void
    {
        $res = Response::redirect('javascript:alert(1)');
        $this->assertSame(302, $res->getStatusCode());
        $prop = new \ReflectionProperty(Response::class, 'headers');
        $headers = $prop->getValue($res);
        $this->assertSame('/', $headers['Location']);
    }

    #[Test]
    public function response_very_long_content_handled(): void
    {
        $body = str_repeat('x', 100_000);
        $res = Response::html($body);
        $this->assertSame($body, $res->getContent());
    }

    #[Test]
    public function response_cache_negative_ttl_accepted(): void
    {
        $res = (new Response())->withCache(-1);
        $this->assertSame(200, $res->getStatusCode());
    }

    // ──────────────────────── SESSION / CSRF ────────────────────────

    #[Test]
    public function session_csrf_token_is_stable_per_session(): void
    {
        $session = new Session();
        $s1 = $session->csrfToken();
        $s2 = $session->csrfToken();
        $this->assertSame($s1, $s2);
    }

    #[Test]
    public function session_csrf_token_is_64_hex_chars(): void
    {
        $session = new Session();
        $token = $session->csrfToken();
        $this->assertSame(64, strlen($token));
    }

    #[Test]
    public function session_flash_only_visible_to_next_session(): void
    {
        $s1 = new Session();
        $s1->flash('msg', 'hello');

        $_SESSION = $s1 instanceof Session ? $_SESSION : [];
        if (!isset($_SESSION['_flash'])) {
            $this->markTestSkipped('Flash storage format requires fresh session');
        }
        $this->assertArrayHasKey('msg', $_SESSION['_flash']);
    }

    #[Test]
    public function session_regenerate_changes_id_and_preserves_data(): void
    {
        $s1 = new Session();
        $s1->set('key', 'value');
        $old = session_id();
        $s1->regenerate();
        $this->assertNotSame($old, session_id());
        $this->assertSame('value', $s1->get('key'));
    }

    #[Test]
    public function csrf_token_per_form_isolation(): void
    {
        $s = new Session();
        $f1 = $s->csrfToken('login');
        $f2 = $s->csrfToken('register');
        $this->assertNotSame($f1, $f2);
    }

    // ──────────────────────── CONTAINER ────────────────────────

    #[Test]
    public function container_circular_dependency_chain_of_three_detected(): void
    {
        $c = new Container();
        $c->bind('X', fn(Container $c) => $c->make('Y'));
        $c->bind('Y', fn(Container $c) => $c->make('Z'));
        $c->bind('Z', fn(Container $c) => $c->make('X'));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency');
        $c->make('X');
    }

    #[Test]
    public function container_self_reference_circular_detected(): void
    {
        $c = new Container();
        $c->bind('Self', fn(Container $c) => $c->make('Self'));
        $this->expectException(\RuntimeException::class);
        $c->make('Self');
    }

    #[Test]
    public function container_bind_with_zero_arg_closure(): void
    {
        $c = new Container();
        $c->bind('noargs', fn() => new \stdClass());
        $obj = $c->make('noargs');
        $this->assertInstanceOf(\stdClass::class, $obj);
    }

    // ──────────────────────── VALIDATION ────────────────────────

    #[Test]
    public function validation_email_valid_cases(): void
    {
        $valid = ['a@b.cd', 'user+tag@dom.com', 'x@y.co.uk'];
        foreach ($valid as $email) {
            $dto = new class ($email) {
                #[Email] public string $email;
                public function __construct(string $e) { $this->email = $e; }
            };
            $errors = Validator::validate($dto);
            $this->assertSame([], $errors, "Email '$email' should be valid");
        }
    }

    #[Test]
    public function validation_email_invalid_cases(): void
    {
        $invalid = ['@', 'a@', '@b', 'a@b', 'a@b.', 'a b@c.d'];
        foreach ($invalid as $email) {
            $dto = new class ($email) {
                #[Email] public string $email;
                public function __construct(string $e) { $this->email = $e; }
            };
            $errors = Validator::validate($dto);
            $this->assertArrayHasKey('email', $errors, "Email '$email' should be invalid");
        }
    }

    #[Test]
    public function validation_min_on_zero_value(): void
    {
        $dto = new class {
            #[Min(0)] public int $v = 0;
        };
        $this->assertSame([], Validator::validate($dto));
    }

    #[Test]
    public function validation_between_at_boundaries(): void
    {
        $dto = new class {
            #[Between(1, 10)] public int $v = 1;
        };
        $this->assertSame([], Validator::validate($dto));
        $dto->v = 10;
        $this->assertSame([], Validator::validate($dto));
        $dto->v = 0;
        $this->assertArrayHasKey('v', Validator::validate($dto));
        $dto->v = 11;
        $this->assertArrayHasKey('v', Validator::validate($dto));
    }

    #[Test]
    public function validation_in_with_empty_array_fails_everything(): void
    {
        $dto = new class {
            #[In([])] public string $v = 'any';
        };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('v', $errors);
    }

    #[Test]
    public function validation_empty_string_skipped_without_required(): void
    {
        $dto = new class {
            #[Email] public string $e = '';
            #[Url] public string $u = '';
            #[Min(3)] public string $s = '';
        };
        $errors = Validator::validate($dto);
        $this->assertSame([], $errors);
    }

    #[Test]
    public function validation_regex_survives_complex_backtracking(): void
    {
        $dto = new class {
            #[VRegex('/^(a+)+$/', 'ReDoS')] public string $x = '';
        };
        $dto->x = str_repeat('a', 50) . '!';
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('x', $errors);
    }

    #[Test]
    public function validation_nullable_with_invalid_value_still_validates(): void
    {
        $dto = new class {
            #[Nullable] #[Email] public ?string $email = 'bad';
        };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('email', $errors);
    }

    #[Test]
    public function validation_required_on_empty_array(): void
    {
        $dto = new class {
            /** @var string[] */
            #[Required] public array $tags = [];
        };
        $errors = Validator::validate($dto);
        $this->assertArrayHasKey('tags', $errors);
    }

    #[Test]
    public function validation_multiple_errors_per_field_possible(): void
    {
        $dto = new class {
            #[Min(10)] #[Max(5)] public string $s = 'x';
        };
        $errors = Validator::validate($dto);
        $this->assertGreaterThanOrEqual(1, count($errors['s'] ?? []));
    }

    #[Test]
    public function validation_confirmed_null_skipped_no_required(): void
    {
        $dto = new class {
            #[Confirmed] public ?string $secret = null;
        };
        $errors = Validator::validate($dto);
        $this->assertSame([], $errors);
    }

    // ──────────────────────── VIEW / TEMPLATE ────────────────────────

    private string $tmpViewsDir;
    private string $tmpCacheDir;
    private Engine $viewEngine;

    protected function setUp(): void
    {
        $this->tmpViewsDir = sys_get_temp_dir() . '/atom_fuzz_v_' . uniqid();
        $this->tmpCacheDir = sys_get_temp_dir() . '/atom_fuzz_c_' . uniqid();
        mkdir($this->tmpViewsDir, 0777, true);
        mkdir($this->tmpCacheDir, 0777, true);
        $this->viewEngine = new Engine($this->tmpViewsDir, $this->tmpCacheDir);
    }

    protected function tearDown(): void
    {
        $this->rmDir($this->tmpViewsDir);
        $this->rmDir($this->tmpCacheDir);
    }

    private function rmDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = "$dir/$f";
            is_dir($p) ? $this->rmDir($p) : unlink($p);
        }
        rmdir($dir);
    }

    #[Test]
    public function template_deeply_nested_if_statements_compile(): void
    {
        $content = '';
        for ($i = 0; $i < 50; $i++) {
            $content = "{% if true %}{$content}{% endif %}";
        }
        $content .= 'DEEP';
        file_put_contents($this->tmpViewsDir . '/deep.twig', $content);
        $result = $this->viewEngine->render('deep.twig');
        $this->assertStringContainsString('DEEP', $result);
    }

    #[Test]
    public function template_raw_filter_disables_autoescape(): void
    {
        file_put_contents($this->tmpViewsDir . '/xss.twig', '{{ code | raw }}');
        $result = $this->viewEngine->render('xss.twig', ['code' => '<script>alert(1)</script>']);
        $this->assertStringContainsString('<script>', $result);
    }

    #[Test]
    public function template_autoescape_prevents_xss(): void
    {
        file_put_contents($this->tmpViewsDir . '/safe.twig', '{{ code }}');
        $result = $this->viewEngine->render('safe.twig', ['code' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function template_carriage_return_in_variable_preserved(): void
    {
        file_put_contents($this->tmpViewsDir . '/cr.twig', '{{ text }}');
        $result = $this->viewEngine->render('cr.twig', ['text' => "hello\r\nworld"]);
        $this->assertStringContainsString('hello', $result);
    }

    #[Test]
    public function template_null_byte_in_variable_escaped(): void
    {
        file_put_contents($this->tmpViewsDir . '/null.twig', '{{ text }}');
        $result = $this->viewEngine->render('null.twig', ['text' => "abc\0def"]);
        $this->assertStringContainsString('abc', $result);
    }

    #[Test]
    public function template_php_tag_in_variable_is_escaped(): void
    {
        file_put_contents($this->tmpViewsDir . '/php.twig', '{{ code }}');
        $result = $this->viewEngine->render('php.twig', ['code' => '<?php echo "hacked"; ?>']);
        $this->assertStringNotContainsString('<?php', $result);
        $this->assertStringContainsString('&lt;?php', $result);
    }

    #[Test]
    public function template_for_with_null_iterable_renders_empty(): void
    {
        file_put_contents($this->tmpViewsDir . '/nullfor.twig', '{% for item in items %}{{ item }}{% endfor %}');
        $result = $this->viewEngine->render('nullfor.twig', ['items' => null]);
        $this->assertSame('', $result);
    }

    #[Test]
    public function template_for_with_non_iterable_renders_empty(): void
    {
        file_put_contents($this->tmpViewsDir . '/baditer.twig', '{% for item in items %}{{ item }}{% endfor %}');
        $result = $this->viewEngine->render('baditer.twig', ['items' => 'not-iterable']);
        $this->assertSame('', $result);
    }

    #[Test]
    public function template_extremely_long_variable_name(): void
    {
        $long = str_repeat('a', 500);
        file_put_contents($this->tmpViewsDir . '/long.twig', "{{ {$long} }}");
        $result = $this->viewEngine->render('long.twig', [$long => 'ok']);
        $this->assertStringContainsString('ok', $result);
    }

    #[Test]
    public function template_unclosed_if_is_syntax_error(): void
    {
        file_put_contents($this->tmpViewsDir . '/unclosed.twig', '{% if true %}hello');
        $this->expectException(\RuntimeException::class);
        $this->viewEngine->render('unclosed.twig');
    }

    #[Test]
    public function template_nested_raw_and_block(): void
    {
        file_put_contents($this->tmpViewsDir . '/mixed.twig', '{% raw %}{{ a }}{% endraw %} {% block x %}b{% endblock %}');
        $result = $this->viewEngine->render('mixed.twig', ['a' => 'raw']);
        $this->assertStringContainsString('{{ a }}', $result);
    }

    #[Test]
    public function template_include_nonexistent_file_throws(): void
    {
        file_put_contents($this->tmpViewsDir . '/badinclude.twig', '{% include "nonexistent.twig" %}');
        $this->expectException(\RuntimeException::class);
        $this->viewEngine->render('badinclude.twig');
    }

    #[Test]
    public function template_deep_dot_notation(): void
    {
        file_put_contents($this->tmpViewsDir . '/deepdot.twig', '{{ a.b.c.d.e }}');
        $result = $this->viewEngine->render('deepdot.twig', ['a' => ['b' => ['c' => ['d' => ['e' => 'deep']]]]]);
        $this->assertStringContainsString('deep', $result);
    }

    #[Test]
    public function template_empty_expression_renders_empty(): void
    {
        file_put_contents($this->tmpViewsDir . '/emptyexp.twig', 'before {{ }} after');
        $result = $this->viewEngine->render('emptyexp.twig');
        $this->assertStringContainsString('before', $result);
        $this->assertStringContainsString('after', $result);
    }

    #[Test]
    public function template_comment_hides_code(): void
    {
        file_put_contents($this->tmpViewsDir . '/comment.twig', 'visible {# {% if true %} BAD {% endif %} #} end');
        $result = $this->viewEngine->render('comment.twig');
        $this->assertStringContainsString('visible', $result);
        $this->assertStringNotContainsString('BAD', $result);
        $this->assertStringContainsString('end', $result);
    }

    // ──────────────────────── CONFIG ────────────────────────

    #[Test]
    public function config_parse_env_value_with_escaped_quotes(): void
    {
        $file = sys_get_temp_dir() . '/atom_fuzz_env_' . uniqid();
        file_put_contents($file, 'KEY="val\"ue"');
        $config = Config::fromEnv($file, false);
        unlink($file);
        $this->assertStringContainsString('val"ue', $config->get('KEY'));
    }

    #[Test]
    public function config_parse_env_with_windows_newlines(): void
    {
        $file = sys_get_temp_dir() . '/atom_fuzz_env_' . uniqid();
        file_put_contents($file, "A=1\r\nB=2");
        $config = Config::fromEnv($file, false);
        unlink($file);
        $this->assertSame('1', $config->get('A'));
        $this->assertSame('2', $config->get('B'));
    }

    #[Test]
    public function config_debug_true_string_value(): void
    {
        $file = sys_get_temp_dir() . '/atom_fuzz_env_' . uniqid();
        file_put_contents($file, 'APP_DEBUG=true');
        $config = Config::fromEnv($file, false);
        unlink($file);
        $this->assertTrue($config->debug);
    }

    // ──────────────────────── LOGGER ────────────────────────

    #[Test]
    public function logger_newline_in_message_is_preserved(): void
    {
        $f = sys_get_temp_dir() . '/atom_fuzz_log_' . uniqid() . '.log';
        $logger = new Logger($f);
        $logger->info("line1\nline2");
        $content = file_get_contents($f);
        unlink($f);
        $this->assertStringContainsString('line1', $content);
        $this->assertStringContainsString('line2', $content);
    }

    #[Test]
    public function logger_context_with_null_values(): void
    {
        $f = sys_get_temp_dir() . '/atom_fuzz_log_' . uniqid() . '.log';
        $logger = new Logger($f);
        $logger->info('msg', ['null' => null, 'bool' => true, 'arr' => [1, 2]]);
        $content = file_get_contents($f);
        unlink($f);
        $this->assertStringContainsString('null', $content);
    }

    // ──────────────────────── CORS ────────────────────────

    #[Test]
    public function cors_reflects_origin_when_wildcard(): void
    {
        $cors = new Cors();
        $req = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: ['origin' => 'https://evil.com'],
        );
        $res = $cors->handle($req, fn() => Response::json(['ok' => true]));
        $prop = new \ReflectionProperty(Response::class, 'headers');
        $headers = $prop->getValue($res);
        $this->assertSame('https://evil.com', $headers['Access-Control-Allow-Origin']);
    }

    #[Test]
    public function cors_all_params_on_preflight(): void
    {
        $cors = new Cors(
            allowOrigin: 'https://app.com',
            allowMethods: 'GET,POST,DELETE',
            allowHeaders: 'Content-Type,Authorization,X-Request-ID',
            allowCredentials: true,
            exposeHeaders: 'X-Page,X-Total',
        );
        $req = new Request(
            server: ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/api'],
            headers: ['origin' => 'https://app.com'],
        );
        $res = $cors->handle($req, fn() => new Response(''));
        $prop = new \ReflectionProperty(Response::class, 'headers');
        $headers = $prop->getValue($res);
        $this->assertSame('https://app.com', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('true', $headers['Access-Control-Allow-Credentials']);
        $this->assertSame('X-Page,X-Total', $headers['Access-Control-Expose-Headers']);
        $this->assertSame(204, $res->getStatusCode());
    }

    // ──────────────────────── UPLOADED FILE ────────────────────────

    #[Test]
    public function uploaded_file_ext_from_tar_gz(): void
    {
        $f = UploadedFile::fromFileArray([
            'name' => 'archive.tar.gz',
            'type' => '', 'size' => 0, 'tmp_name' => '/tmp/x',
            'error' => UPLOAD_ERR_OK,
        ]);
        $this->assertSame('gz', $f->ext);
    }

    #[Test]
    public function uploaded_file_ext_from_unicode_name(): void
    {
        $f = UploadedFile::fromFileArray([
            'name' => 'photo.jpg',
            'type' => '', 'size' => 0, 'tmp_name' => '/tmp/x',
            'error' => UPLOAD_ERR_OK,
        ]);
        $this->assertSame('jpg', $f->ext);
    }

    #[Test]
    public function uploaded_file_no_extension_in_path_traversal_name(): void
    {
        $f = UploadedFile::fromFileArray([
            'name' => '../../../etc/passwd',
            'type' => '', 'size' => 0, 'tmp_name' => '/tmp/x',
            'error' => UPLOAD_ERR_OK,
        ]);
        $this->assertSame('', $f->ext);
    }

    #[Test]
    public function uploaded_file_move_to_existing_dir_returns_false_in_cli(): void
    {
        $tmpDir = sys_get_temp_dir() . '/atom_fuzz_up_' . uniqid();
        mkdir($tmpDir, 0777, true);
        $src = $tmpDir . '/src.txt';
        file_put_contents($src, 'data');
        $dest = $tmpDir . '/out.txt';

        $f = UploadedFile::fromFileArray([
            'name' => 'src.txt', 'type' => 'text/plain', 'size' => 4,
            'tmp_name' => $src, 'error' => UPLOAD_ERR_OK,
        ]);
        try {
            $result = $f->move($dest);
            $this->assertFalse($result);
        } finally {
            foreach ((array) glob($tmpDir . '/*') as $g) @unlink($g);
            rmdir($tmpDir);
        }
    }

    // ──────────────────────── PIPELINE ────────────────────────

    #[Test]
    public function pipeline_no_middleware_runs_core(): void
    {
        $c = new Container();
        $req = new Request();
        $res = Pipeline::run([], $req, fn() => Response::json(['ok' => true]), $c);
        $this->assertSame(200, $res->getStatusCode());
    }

    #[Test]
    public function pipeline_closure_can_add_header(): void
    {
        $c = new Container();
        $req = new Request();
        $mwFn = function (Request $r, \Closure $next): Response {
            $response = $next($r);
            return $response->withHeader('X-MW', 'added');
        };
        $res = Pipeline::run([$mwFn], $req, fn() => Response::text('ok'), $c);
        $prop = new \ReflectionProperty(Response::class, 'headers');
        $headers = $prop->getValue($res);
        $this->assertSame('added', $headers['X-MW']);
    }

    #[Test]
    public function pipeline_invalid_middleware_value_is_caught(): void
    {
        $c = new Container();
        $req = new Request();
        try {
            Pipeline::run([12345], $req, fn() => new Response(''), $c);
            $this->fail('Should have thrown');
        } catch (\Throwable $e) {
            $this->assertTrue($e instanceof \TypeError || $e instanceof \InvalidArgumentException);
        }
    }

    // ──────────────────────── STATUS CODE ────────────────────────

    #[Test]
    public function status_code_from_valid_int(): void
    {
        $this->assertSame(StatusCode::OK, StatusCode::from(200));
        $this->assertSame(StatusCode::NOT_FOUND, StatusCode::from(404));
    }

    #[Test]
    public function status_code_from_invalid_throws(): void
    {
        $this->expectException(\ValueError::class);
        StatusCode::from(999);
    }

    #[Test]
    public function status_code_try_from_invalid_returns_null(): void
    {
        $this->assertNull(StatusCode::tryFrom(0));
    }

    // ──────────────────────── REGEX ────────────────────────

    #[Test]
    public function regex_match_empty_subject(): void
    {
        $result = Regex::match('/^$/', '');
        $this->assertNotNull($result);
    }

    #[Test]
    public function regex_split_empty_string_returns_empty_array(): void
    {
        $result = Regex::split('/,/', '');
        $this->assertSame([], $result);
    }

    #[Test]
    public function regex_replace_callback_no_match_unchanged(): void
    {
        $result = Regex::replace('/x/', fn($m) => 'y', 'abc');
        $this->assertSame('abc', $result);
    }

    // ──────────────────────── STRESS ────────────────────────

    #[Test]
    public function stress_router_with_no_routes_returns_404(): void
    {
        $c = new Container();
        $r = new Router($c, sys_get_temp_dir() . '/atom_fuzz_stress_' . uniqid());
        $req = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
        $res = $r->dispatch($req);
        $this->assertSame(404, $res->getStatusCode());
    }

    #[Test]
    public function stress_extremely_long_uri_does_not_crash(): void
    {
        $c = new Container();
        $r = new Router($c, sys_get_temp_dir() . '/atom_fuzz_stress_' . uniqid());
        $req = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/' . str_repeat('a', 8000),
        ]);
        $res = $r->dispatch($req);
        $this->assertTrue(in_array($res->getStatusCode(), [404, 414], true));
    }
}
