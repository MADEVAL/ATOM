<?php
declare(strict_types=1);
namespace Atom\Tests\Http;

use Atom\Http\StatusCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(StatusCode::class)]
final class StatusCodeTest extends TestCase
{
    #[Test]
    public function ok_has_value_200(): void
    {
        $this->assertSame(200, StatusCode::OK->value);
    }

    #[Test]
    public function created_has_value_201(): void
    {
        $this->assertSame(201, StatusCode::CREATED->value);
    }

    #[Test]
    public function no_content_has_value_204(): void
    {
        $this->assertSame(204, StatusCode::NO_CONTENT->value);
    }

    #[Test]
    public function moved_has_value_301(): void
    {
        $this->assertSame(301, StatusCode::MOVED->value);
    }

    #[Test]
    public function found_has_value_302(): void
    {
        $this->assertSame(302, StatusCode::FOUND->value);
    }

    #[Test]
    public function not_modified_has_value_304(): void
    {
        $this->assertSame(304, StatusCode::NOT_MODIFIED->value);
    }

    #[Test]
    public function bad_request_has_value_400(): void
    {
        $this->assertSame(400, StatusCode::BAD_REQUEST->value);
    }

    #[Test]
    public function unauthorized_has_value_401(): void
    {
        $this->assertSame(401, StatusCode::UNAUTHORIZED->value);
    }

    #[Test]
    public function forbidden_has_value_403(): void
    {
        $this->assertSame(403, StatusCode::FORBIDDEN->value);
    }

    #[Test]
    public function not_found_has_value_404(): void
    {
        $this->assertSame(404, StatusCode::NOT_FOUND->value);
    }

    #[Test]
    public function method_not_allowed_has_value_405(): void
    {
        $this->assertSame(405, StatusCode::METHOD_NOT_ALLOWED->value);
    }

    #[Test]
    public function server_error_has_value_500(): void
    {
        $this->assertSame(500, StatusCode::SERVER_ERROR->value);
    }

    #[Test]
    public function from_creates_from_int(): void
    {
        $this->assertSame(StatusCode::OK, StatusCode::from(200));
        $this->assertSame(StatusCode::NOT_FOUND, StatusCode::from(404));
    }

    #[Test]
    public function from_invalid_throws(): void
    {
        $this->expectException(\ValueError::class);
        StatusCode::from(999);
    }

    #[Test]
    public function tryFrom_returns_null_for_invalid(): void
    {
        $this->assertNull(StatusCode::tryFrom(999));
        $this->assertSame(StatusCode::OK, StatusCode::tryFrom(200));
    }

    #[Test]
    public function all_cases_exist(): void
    {
        $cases = StatusCode::cases();
        $this->assertCount(19, $cases);
    }

    #[Test]
    public function name_matches_expectation(): void
    {
        $this->assertSame('OK', StatusCode::OK->name);
        $this->assertSame('NOT_FOUND', StatusCode::NOT_FOUND->name);
    }
}
