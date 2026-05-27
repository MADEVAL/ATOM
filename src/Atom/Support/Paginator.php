<?php
declare(strict_types=1);
namespace Atom\Support;

use Atom\Http\Request;

final class Paginator
{
    public int $page;
    public int $perPage;
    public int $offset;
    public int $total = 0;
    public int $pages = 0;
    public array $items = [];

    /** Private constructor; use Paginator::from() factory instead */
    private function __construct(int $page, int $perPage)
    {
        $this->page = $page;
        $this->perPage = $perPage;
        $this->offset = ($page - 1) * $perPage;
    }

    public static function from(Request $req, int $defaultPerPage = 20): self
    {
        $page = max(1, (int) ($req->input('page', '1')));
        $perPage = min(\Atom\Constants::PAGINATOR_MAX_PER_PAGE, max(1, (int) ($req->input('per_page', (string) $defaultPerPage))));
        return new self($page, $perPage);
    }

    public static function make(int $page, int $perPage): self
    {
        return new self(max(1, $page), min(\Atom\Constants::PAGINATOR_MAX_PER_PAGE, max(1, $perPage)));
    }

    /** @param list<array<string,mixed>> $items */
    public function paginate(array $items, int $total): array
    {
        $this->items = $items;
        $this->total = $total;
        $this->pages = (int) ceil($total / $this->perPage);
        return $this->toArray();
    }

    public function sqlLimit(): string
    {
        return "LIMIT {$this->perPage} OFFSET {$this->offset}";
    }

    /** @return array{data:list<array>,page:int,perPage:int,total:int,pages:int} */
    public function toArray(): array
    {
        return [
            'data'    => $this->items,
            'page'    => $this->page,
            'perPage' => $this->perPage,
            'total'   => $this->total,
            'pages'   => $this->pages,
        ];
    }
}
