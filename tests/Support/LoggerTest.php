<?php
declare(strict_types=1);
namespace Atom\Tests\Support;

use Atom\Support\Logger;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Logger::class)]
final class LoggerTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/atom_log_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) unlink($this->tmpFile);
    }

    #[Test]
    public function writes_info_message(): void
    {
        $logger = new Logger($this->tmpFile);
        $logger->info('test message');
        $content = file_get_contents($this->tmpFile);
        $this->assertStringContainsString('INFO: test message', $content);
    }

    #[Test]
    public function writes_debug_message(): void
    {
        $logger = new Logger($this->tmpFile);
        $logger->debug('debug info');
        $content = file_get_contents($this->tmpFile);
        $this->assertStringContainsString('DEBUG: debug info', $content);
    }

    #[Test]
    public function filters_below_min_level(): void
    {
        $logger = new Logger($this->tmpFile, Logger::WARN);
        $logger->debug('should not appear');
        $logger->info('also silent');
        $logger->warn('this appears');
        $content = file_get_contents($this->tmpFile);
        $this->assertStringNotContainsString('should not appear', $content);
        $this->assertStringNotContainsString('also silent', $content);
        $this->assertStringContainsString('WARN: this appears', $content);
    }

    #[Test]
    public function writes_error_with_context(): void
    {
        $logger = new Logger($this->tmpFile);
        $logger->error('DB failure', ['code' => 500, 'db' => 'mysql']);
        $content = file_get_contents($this->tmpFile);
        $this->assertStringContainsString('ERROR: DB failure', $content);
        $this->assertStringContainsString('"code":500', $content);
    }

    #[Test]
    public function includes_timestamp(): void
    {
        $logger = new Logger($this->tmpFile);
        $logger->info('stamped');
        $content = file_get_contents($this->tmpFile);
        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $content);
    }

    #[Test]
    public function level_constants_are_increasing(): void
    {
        $this->assertGreaterThan(Logger::INFO, Logger::WARN);
        $this->assertGreaterThan(Logger::WARN, Logger::ERROR);
    }
}
