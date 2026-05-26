<?php
declare(strict_types=1);
namespace Atom\Tests\Console;

use Atom\Application;
use Atom\Config;
use Atom\Console\Console;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Console::class)]
final class ConsoleTest extends TestCase
{
    private Application $app;
    private string $tmpCache;

    protected function setUp(): void
    {
        $this->tmpCache = sys_get_temp_dir() . '/atom_console_' . uniqid();
        mkdir($this->tmpCache, 0777, true);
        $this->app = new Application(new Config(cacheDir: $this->tmpCache));
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->tmpCache . '/*.php') as $f) unlink($f);
        if (is_dir($this->tmpCache)) rmdir($this->tmpCache);
    }

    #[Test]
    public function list_shows_commands(): void
    {
        $console = new Console($this->app);
        ob_start();
        $code = $console->run(['atom', 'list']);
        $output = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('routes', $output);
        $this->assertStringContainsString('cache', $output);
    }

    #[Test]
    public function routes_shows_registered_routes(): void
    {
        $this->app->router->get('/test', 'TestController@index', 'test.route');
        $this->app->router->post('/api', 'ApiController@create');

        $console = new Console($this->app);
        ob_start();
        $code = $console->run(['atom', 'routes']);
        $output = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('/test', $output);
        $this->assertStringContainsString('TestController', $output);
        $this->assertStringContainsString('/api', $output);
    }

    #[Test]
    public function routes_shows_empty_message(): void
    {
        $console = new Console($this->app);
        ob_start();
        $code = $console->run(['atom', 'routes']);
        $output = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('No routes', $output);
    }

    #[Test]
    public function custom_commands(): void
    {
        $console = new Console($this->app);
        $console->add('hello', function () { echo 'Hello CLI'; return 0; });
        ob_start();
        $code = $console->run(['atom', 'hello']);
        $output = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertSame('Hello CLI', $output);
    }

    #[Test]
    public function unknown_command(): void
    {
        $console = new Console($this->app);
        ob_start();
        $code = $console->run(['atom', 'nonexistent']);
        $output = ob_get_clean();
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Unknown command', $output);
    }

    #[Test]
    public function clear_cache(): void
    {
        file_put_contents($this->tmpCache . '/routes.php', '<?php return [];');
        file_put_contents($this->tmpCache . '/other.php', '<?php return [];');

        $console = new Console($this->app);
        ob_start();
        $code = $console->run(['atom', 'cache']);
        $output = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Cleared 2', $output);
    }

    #[Test]
    public function clear_cache_no_dir(): void
    {
        $app = new Application();
        $console = new Console($app);
        ob_start();
        $code = $console->run(['atom', 'cache']);
        $output = ob_get_clean();
        $this->assertSame(1, $code);
    }

    #[Test]
    public function command_returning_null_defaults_to_zero(): void
    {
        $console = new Console($this->app);
        $console->add('nullreturn', fn() => null);
        ob_start();
        $code = $console->run(['atom', 'nullreturn']);
        ob_end_clean();
        $this->assertSame(0, $code);
    }

    #[Test]
    public function command_receives_positional_args(): void
    {
        $console = new Console($this->app);
        $console->add('greet', function (array $args, array $options): int {
            echo "Hello {$args[0]}";
            return 0;
        });
        ob_start();
        $code = $console->run(['atom', 'greet', 'World']);
        $output = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertSame('Hello World', $output);
    }

    #[Test]
    public function command_receives_options(): void
    {
        $console = new Console($this->app);
        $console->add('test', function (array $args, array $options): int {
            echo $options['verbose'] ? 'verbose' : 'quiet';
            return 0;
        });
        ob_start();
        $code = $console->run(['atom', 'test', '--verbose']);
        $output = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertSame('verbose', $output);
    }

    #[Test]
    public function command_receives_key_value_options(): void
    {
        $console = new Console($this->app);
        $console->add('config', function (array $args, array $options): int {
            echo "db={$options['db']}";
            return 0;
        });
        ob_start();
        $code = $console->run(['atom', 'config', '--db=mysql']);
        $output = ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertSame('db=mysql', $output);
    }

    #[Test]
    public function routes_output_contains_color_codes(): void
    {
        $this->app->router->get('/test', 'TestController@index');
        $console = new Console($this->app);
        ob_start();
        $console->run(['atom', 'routes']);
        $output = ob_get_clean();
        $this->assertStringContainsString("\033", $output);
    }

    #[Test]
    public function list_output_contains_color_codes(): void
    {
        $console = new Console($this->app);
        ob_start();
        $console->run(['atom', 'list']);
        $output = ob_get_clean();
        $this->assertStringContainsString("\033", $output);
    }
}
