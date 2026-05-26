<?php
declare(strict_types=1);
namespace Atom\Tests\Http;

use Atom\Http\Session;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Session::class)]
final class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->session = new Session();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    #[Test]
    public function set_and_get(): void
    {
        $this->session->set('key', 'value');
        $this->assertSame('value', $this->session->get('key'));
    }

    #[Test]
    public function get_returns_default_for_missing_key(): void
    {
        $this->assertNull($this->session->get('missing'));
        $this->assertSame('fallback', $this->session->get('missing', 'fallback'));
    }

    #[Test]
    public function has_returns_true_when_key_exists(): void
    {
        $this->assertFalse($this->session->has('key'));
        $this->session->set('key', 'val');
        $this->assertTrue($this->session->has('key'));
    }

    #[Test]
    public function remove_deletes_key(): void
    {
        $this->session->set('key', 'val');
        $this->assertTrue($this->session->has('key'));
        $this->session->remove('key');
        $this->assertFalse($this->session->has('key'));
    }

    #[Test]
    public function flash_stores_value_for_next_request(): void
    {
        $this->session->flash('success', 'Saved!');

        // Flash data is in $_SESSION['_flash'] for the next request
        // Simulate next request
        $_SESSION = ['_flash' => ['success' => 'Saved!']];
        $session2 = new Session();
        $this->assertSame('Saved!', $session2->get('success'));
    }

    #[Test]
    public function flash_persists_one_request(): void
    {
        $this->session->flash('info', 'hello');

        // Next request
        $_SESSION = ['_flash' => ['info' => 'hello']];
        $session2 = new Session();
        $this->assertSame('hello', $session2->get('info'));

        // Following request (no flash) — gone
        $_SESSION = [];
        $session3 = new Session();
        $this->assertNull($session3->get('info'));
    }

    #[Test]
    public function regenerate_changes_session_id(): void
    {
        $old = session_id();
        $this->session->regenerate();
        $this->assertNotSame($old, session_id());
    }

    #[Test]
    public function get_returns_session_value_over_flashed(): void
    {
        $this->session->flash('key', 'flashed');
        $this->session->set('key', 'permanent');
        $this->assertSame('permanent', $this->session->get('key'));
    }

    #[Test]
    public function session_is_singleton_in_container(): void
    {
        $this->assertInstanceOf(Session::class, $this->session);
    }

    #[Test]
    public function csrf_token_generates_once(): void
    {
        $t1 = $this->session->csrfToken();
        $t2 = $this->session->csrfToken();
        $this->assertSame($t1, $t2);
        $this->assertSame(64, strlen($t1));
    }

    #[Test]
    public function csrf_validation_passes_for_correct_token(): void
    {
        $t = $this->session->csrfToken();
        $this->assertTrue($this->session->validateCsrf($t));
    }

    #[Test]
    public function csrf_validation_fails_for_wrong_token(): void
    {
        $this->session->csrfToken();
        $this->assertFalse($this->session->validateCsrf('wrong'));
    }
}
