<?php
declare(strict_types=1);
namespace Atom\Tests\Http;

use Atom\Http\Response;
use Atom\Http\StatusCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    #[Test]
    public function constructor_with_defaults(): void
    {
        $res = new Response();
        $this->assertSame('', $res->getContent());
        $this->assertSame(200, $res->getStatusCode());
    }

    #[Test]
    public function constructor_with_content_and_status(): void
    {
        $res = new Response('body', StatusCode::NOT_FOUND);
        $this->assertSame('body', $res->getContent());
        $this->assertSame(404, $res->getStatusCode());
    }

    #[Test]
    public function constructor_with_int_status(): void
    {
        $res = new Response('', 201);
        $this->assertSame(201, $res->getStatusCode());
    }

    #[Test]
    public function html_creates_with_content_type(): void
    {
        $res = Response::html('<h1>Test</h1>');
        $this->assertSame('<h1>Test</h1>', $res->getContent());
        $this->assertSame(200, $res->getStatusCode());
    }

    #[Test]
    public function html_with_custom_status(): void
    {
        $res = Response::html('<h1>404</h1>', StatusCode::NOT_FOUND);
        $this->assertSame(404, $res->getStatusCode());
    }

    #[Test]
    public function json_encodes_data(): void
    {
        $res = Response::json(['key' => 'value']);
        $decoded = json_decode($res->getContent(), true);
        $this->assertSame(['key' => 'value'], $decoded);
    }

    #[Test]
    public function json_handles_unicode(): void
    {
        $res = Response::json(['text' => 'unicode']);
        $this->assertStringContainsString('unicode', $res->getContent());
    }

    #[Test]
    public function json_with_status(): void
    {
        $res = Response::json(['error' => 'Not Found'], StatusCode::NOT_FOUND);
        $this->assertSame(404, $res->getStatusCode());
    }

    #[Test]
    public function json_invalid_data_throws(): void
    {
        $this->expectException(\JsonException::class);
        Response::json("\xB1\x31");
    }

    #[Test]
    public function redirect_creates_with_location(): void
    {
        $res = Response::redirect('/login');
        $this->assertSame('', $res->getContent());
        $this->assertSame(302, $res->getStatusCode());
    }

    #[Test]
    public function redirect_with_custom_status(): void
    {
        $res = Response::redirect('/new', StatusCode::MOVED);
        $this->assertSame(301, $res->getStatusCode());
    }

    #[Test]
    public function with_header_returns_new_instance(): void
    {
        $res1 = new Response();
        $res2 = $res1->withHeader('X-Test', 'value');
        $this->assertNotSame($res1, $res2);
    }

    #[Test]
    public function with_status_returns_new_instance(): void
    {
        $res1 = new Response();
        $res2 = $res1->withStatus(StatusCode::CREATED);
        $this->assertNotSame($res1, $res2);
        $this->assertSame(201, $res2->getStatusCode());
        $this->assertSame(200, $res1->getStatusCode());
    }

    #[Test]
    public function send_outputs_content(): void
    {
        $res = new Response('Hello World');
        ob_start();
        $res->send();
        $output = ob_get_clean();
        $this->assertSame('Hello World', $output);
    }

    #[Test]
    public function send_with_headers(): void
    {
        $res = Response::html('<p>Test</p>');
        ob_start();
        $res->send();
        $output = ob_get_clean();
        $this->assertSame('<p>Test</p>', $output);
    }

    #[Test]
    public function get_content_returns_content(): void
    {
        $res = new Response('test content');
        $this->assertSame('test content', $res->getContent());
    }

    #[Test]
    public function get_status_code_returns_int(): void
    {
        $res = new Response(status: StatusCode::FORBIDDEN);
        $this->assertSame(403, $res->getStatusCode());
    }

    #[Test]
    public function chain_withHeader_and_withStatus(): void
    {
        $res = (new Response())
            ->withHeader('X-A', '1')
            ->withStatus(StatusCode::CREATED);

        $this->assertSame(201, $res->getStatusCode());
    }

    #[Test]
    public function with_cookie_returns_new_instance(): void
    {
        $res1 = new Response();
        $res2 = $res1->withCookie('session', 'abc');
        $this->assertNotSame($res1, $res2);
    }

    #[Test]
    public function send_with_cookies_emits_setcookie(): void
    {
        $res = (new Response('ok'))
            ->withCookie('token', 'xyz', 7200, '/api');

        ob_start();
        $res->send();
        $output = ob_get_clean();
        $this->assertSame('ok', $output);
    }

    #[Test]
    public function json_pretty_prints_when_flag_set(): void
    {
        $res = Response::json(['a' => 1, 'b' => 2], pretty: true);
        $this->assertStringContainsString("\n", $res->getContent());
    }

    #[Test]
    public function json_compact_by_default(): void
    {
        $res = Response::json(['a' => 1]);
        $this->assertStringNotContainsString("\n", $res->getContent());
    }

    #[Test]
    public function with_cookie_defaults(): void
    {
        $res = (new Response())->withCookie('k', 'v');
        $this->assertSame(200, $res->getStatusCode());
    }

    #[Test]
    public function header_injection_stripped(): void
    {
        $res = (new Response('content'))->withHeader('X-Clean', "safe\r\nSet-Cookie: hacked=true");
        ob_start();
        $res->send();
        $output = ob_get_clean();
        $this->assertSame('content', $output);
    }

    #[Test]
    public function text_factory_creates_plain_response(): void
    {
        $res = Response::text('plain text');
        $this->assertSame('plain text', $res->getContent());
        $this->assertSame(200, $res->getStatusCode());
    }

    #[Test]
    public function no_content_returns_204(): void
    {
        $res = Response::noContent();
        $this->assertSame('', $res->getContent());
        $this->assertSame(204, $res->getStatusCode());
    }

    #[Test]
    public function with_cache_adds_caching_header(): void
    {
        $res = (new Response('data'))->withCache(3600);
        $prop = new \ReflectionProperty(Response::class, 'headers');
        $headers = $prop->getValue($res);
        $this->assertSame('public, max-age=3600', $headers['Cache-Control']);
    }

    #[Test]
    public function get_content_and_get_status_code(): void
    {
        $res = new Response('body', 201);
        $this->assertSame('body', $res->getContent());
        $this->assertSame(201, $res->getStatusCode());
    }

    #[Test]
    public function header_injection_stripped_from_key(): void
    {
        $res = (new Response('content'))->withHeader("X-Bad\r\nSet-Cookie: evil", 'value');
        ob_start();
        $res->send();
        $output = ob_get_clean();
        $this->assertSame('content', $output);
    }

    #[Test]
    public function with_cookie_with_options_array(): void
    {
        $res = (new Response())->withCookie('test', 'val', ['ttl' => 7200, 'path' => '/app', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
        $this->assertSame(200, $res->getStatusCode());
    }

    #[Test]
    public function redirect_with_permanent_redirect(): void
    {
        $res = Response::redirect('https://new.com', \Atom\Http\StatusCode::PERMANENT_REDIRECT);
        $this->assertSame(308, $res->getStatusCode());
        $prop = new \ReflectionProperty(Response::class, 'headers');
        $headers = $prop->getValue($res);
        $this->assertSame('https://new.com', $headers['Location']);
    }

    #[Test]
    public function redirect_blocks_javascript_protocol(): void
    {
        $res = Response::redirect('javascript:alert(1)');
        $prop = new \ReflectionProperty(Response::class, 'headers');
        $headers = $prop->getValue($res);
        $this->assertSame('/', $headers['Location']);
    }

    #[Test]
    public function redirect_blocks_data_protocol(): void
    {
        $res = Response::redirect('data:text/html,<script>alert(1)</script>');
        $prop = new \ReflectionProperty(Response::class, 'headers');
        $headers = $prop->getValue($res);
        $this->assertSame('/', $headers['Location']);
    }

    #[Test]
    public function send_with_explicit_https_flag(): void
    {
        $res = (new Response('content'))->withCookie('k', 'v', ['secure' => true]);
        ob_start();
        $res->send(isHttps: true);
        $output = ob_get_clean();
        $this->assertSame('content', $output);
    }

    #[Test]
    public function invalid_samesite_falls_back_to_lax(): void
    {
        $res = (new Response('ok'))->withCookie('k', 'v', ['samesite' => 'Invalid']);
        ob_start();
        $res->send();
        $output = ob_get_clean();
        $this->assertSame('ok', $output);
    }

    #[Test]
    public function with_cookie_options_cannot_override_name_and_value(): void
    {
        $res = (new Response('ok'))->withCookie('session', 'abc', ['name' => 'hijack', 'value' => 'x']);
        $prop = new \ReflectionProperty(Response::class, 'cookies');
        $cookies = $prop->getValue($res);
        $this->assertSame('session', $cookies[0]['name']);
        $this->assertSame('abc', $cookies[0]['value']);
    }
}
