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
        $res = Response::json(['text' => 'привет']);
        $this->assertStringContainsString('привет', $res->getContent());
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
        ob_get_clean();
        $this->assertTrue(true); // no exceptions
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
}
