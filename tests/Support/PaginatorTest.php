<?php
declare(strict_types=1);
namespace Atom\Tests\Support;

use Atom\Http\Request;
use Atom\Support\Paginator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Paginator::class)]
final class PaginatorTest extends TestCase
{
    #[Test]
    public function parses_page_and_per_page_from_request(): void
    {
        $req = new Request(query: ['page' => '3', 'per_page' => '10']);
        $p = Paginator::from($req);
        $this->assertSame(3, $p->page);
        $this->assertSame(10, $p->perPage);
        $this->assertSame(20, $p->offset);
    }

    #[Test]
    public function defaults_to_first_page(): void
    {
        $req = new Request();
        $p = Paginator::from($req);
        $this->assertSame(1, $p->page);
        $this->assertSame(20, $p->perPage);
    }

    #[Test]
    public function clamps_page_to_positive(): void
    {
        $req = new Request(query: ['page' => '-5']);
        $p = Paginator::from($req);
        $this->assertSame(1, $p->page);
    }

    #[Test]
    public function clamps_per_page_to_100_max(): void
    {
        $req = new Request(query: ['per_page' => '999']);
        $p = Paginator::from($req);
        $this->assertSame(100, $p->perPage);
    }

    #[Test]
    public function paginate_returns_correct_structure(): void
    {
        $req = new Request(query: ['page' => '1', 'per_page' => '2']);
        $p = Paginator::from($req);
        $result = $p->paginate([['id' => 1], ['id' => 2]], 5);
        $this->assertSame(2, count($result['data']));
        $this->assertSame(1, $result['page']);
        $this->assertSame(2, $result['perPage']);
        $this->assertSame(5, $result['total']);
        $this->assertSame(3, $result['pages']);
    }

    #[Test]
    public function sql_limit_generates_correct_syntax(): void
    {
        $req = new Request(query: ['page' => '2', 'per_page' => '10']);
        $p = Paginator::from($req);
        $this->assertSame('LIMIT 10 OFFSET 10', $p->sqlLimit());
    }
}
