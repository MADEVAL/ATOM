<?php
declare(strict_types=1);
namespace Atom\Tests\Orm;

use Atom\Database\Database;
use Atom\Orm\{Model, Table, PrimaryKey, Column, HasMany, BelongsTo, HasOne, Query};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[Table('users')]
class TestUser extends Model
{
    #[PrimaryKey] public int $id = 0;
    public string $name = '';
    public string $email = '';
    #[Column('created_at')] public ?string $createdAt = null;
    #[Column('updated_at')] public ?string $updatedAt = null;

    public function posts(): HasMany { return $this->hasMany(TestPost::class, 'user_id'); }
    public function avatar(): HasOne { return $this->hasOne(TestAvatar::class, 'user_id'); }
}

#[Table('posts')]
class TestPost extends Model
{
    #[PrimaryKey] public int $id = 0;
    public string $title = '';
    #[Column('user_id')] public int $userId = 0;

    public function user(): BelongsTo { return $this->belongsTo(TestUser::class, 'user_id'); }
}

#[Table('avatars')]
class TestAvatar extends Model
{
    #[PrimaryKey] public int $id = 0;
    public string $url = '';
    #[Column('user_id')] public int $userId = 0;

    public function user(): BelongsTo { return $this->belongsTo(TestUser::class); }
}

#[CoversClass(Model::class)]
#[CoversClass(Query::class)]
#[CoversClass(HasMany::class)]
#[CoversClass(BelongsTo::class)]
#[CoversClass(HasOne::class)]
final class OrmTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $this->db = new Database('sqlite::memory:');
        Model::setConnection($this->db);

        $this->db->run('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, created_at TEXT, updated_at TEXT)');
        $this->db->run('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, user_id INTEGER)');
        $this->db->run('CREATE TABLE avatars (id INTEGER PRIMARY KEY AUTOINCREMENT, url TEXT, user_id INTEGER)');
    }

    // ──────────── CRUD ────────────

    #[Test]
    public function create_and_find(): void
    {
        $u = TestUser::create(['name' => 'Alice', 'email' => 'a@test.com']);
        $this->assertTrue($u->exists());
        $this->assertGreaterThan(0, $u->id);
        $this->assertSame('Alice', $u->name);

        $found = TestUser::find($u->id);
        $this->assertNotNull($found);
        $this->assertSame('Alice', $found->name);
    }

    #[Test]
    public function update(): void
    {
        $u = TestUser::create(['name' => 'Bob', 'email' => 'b@test.com']);
        $u->name = 'Robert';
        $u->save();

        $found = TestUser::find($u->id);
        $this->assertSame('Robert', $found->name);
    }

    #[Test]
    public function delete(): void
    {
        $u = TestUser::create(['name' => 'Del', 'email' => 'd@t.com']);
        $this->assertTrue($u->delete());
        $this->assertFalse($u->exists());
        $this->assertNull(TestUser::find($u->id));
    }

    #[Test]
    public function find_or_fail_throws_on_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        TestUser::findOrFail(99999);
    }

    #[Test]
    public function first_or_create_returns_existing(): void
    {
        $u1 = TestUser::create(['name' => 'Dup', 'email' => 'd@t.com']);
        $u2 = TestUser::firstOrCreate(['email' => 'd@t.com']);
        $this->assertSame($u1->id, $u2->id);
    }

    #[Test]
    public function destroy(): void
    {
        $a = TestUser::create(['name' => 'A', 'email' => 'a@a.com']);
        $b = TestUser::create(['name' => 'B', 'email' => 'b@b.com']);
        TestUser::destroy($a->id, $b->id);
        $this->assertNull(TestUser::find($a->id));
        $this->assertNull(TestUser::find($b->id));
    }

    #[Test]
    public function timestamps_set_on_create(): void
    {
        $u = TestUser::create(['name' => 'Time', 'email' => 't@t.com']);
        $this->assertNotNull($u->createdAt);
        $this->assertNotNull($u->updatedAt);
    }

    #[Test]
    public function table_from_attribute(): void
    {
        $this->assertSame('users', TestUser::table());
        $this->assertSame('posts', TestPost::table());
        $this->assertSame('avatars', TestAvatar::table());
    }

    #[Test]
    public function primary_key_from_attribute(): void
    {
        $this->assertSame('id', TestUser::primaryKey());
    }

    // ──────────── Query ────────────

    #[Test]
    public function where_equals(): void
    {
        TestUser::create(['name' => 'Q1', 'email' => 'q1@t.com']);
        TestUser::create(['name' => 'Q2', 'email' => 'q2@t.com']);

        $results = TestUser::query()->where('name', 'Q1')->get();
        $this->assertCount(1, $results);
    }

    #[Test]
    public function where_operator(): void
    {
        TestUser::create(['name' => 'Alice', 'email' => 'a@t.com']);
        $results = TestUser::query()->where('name', 'LIKE', '%lice%')->get();
        $this->assertCount(1, $results);
    }

    #[Test]
    public function where_in(): void
    {
        TestUser::create(['name' => 'A', 'email' => 'a@t.com']);
        TestUser::create(['name' => 'B', 'email' => 'b@t.com']);
        TestUser::create(['name' => 'C', 'email' => 'c@t.com']);
        $results = TestUser::query()->whereIn('name', ['A', 'C'])->get();
        $this->assertCount(2, $results);
    }

    #[Test]
    public function order_by(): void
    {
        TestUser::create(['name' => 'Zed', 'email' => 'z@t.com']);
        TestUser::create(['name' => 'Alpha', 'email' => 'a@t.com']);
        $results = TestUser::query()->orderBy('name')->get();
        $this->assertSame('Alpha', $results[0]->name);
    }

    #[Test]
    public function order_by_desc(): void
    {
        TestUser::create(['name' => 'A', 'email' => 'a@t.com']);
        TestUser::create(['name' => 'Z', 'email' => 'z@t.com']);
        $results = TestUser::query()->orderByDesc('name')->get();
        $this->assertSame('Z', $results[0]->name);
    }

    #[Test]
    public function limit_and_count(): void
    {
        TestUser::create(['name' => '1', 'email' => '1@t.com']);
        TestUser::create(['name' => '2', 'email' => '2@t.com']);
        TestUser::create(['name' => '3', 'email' => '3@t.com']);
        $this->assertCount(2, TestUser::query()->limit(2)->get());
        $this->assertSame(3, TestUser::query()->count());
    }

    #[Test]
    public function or_where(): void
    {
        TestUser::create(['name' => 'Foo', 'email' => 'f@t.com']);
        TestUser::create(['name' => 'Bar', 'email' => 'b@t.com']);
        $results = TestUser::query()->where('name', 'Foo')->orWhere('name', 'Bar')->get();
        $this->assertCount(2, $results);
    }

    #[Test]
    public function magic_where(): void
    {
        TestUser::create(['name' => 'Magic', 'email' => 'm@t.com']);
        $results = TestUser::query()->whereName('Magic')->get();
        $this->assertCount(1, $results);
    }

    #[Test]
    public function exists(): void
    {
        TestUser::create(['name' => 'E', 'email' => 'e@t.com']);
        $this->assertTrue(TestUser::query()->where('name', 'E')->exists());
        $this->assertFalse(TestUser::query()->where('name', 'Nope')->exists());
    }

    #[Test]
    public function to_array_excludes_primary_key(): void
    {
        $u = TestUser::create(['name' => 'Arr', 'email' => 'arr@t.com']);
        $arr = $u->toArray();
        $this->assertArrayNotHasKey('id', $arr);
        $this->assertSame('Arr', $arr['name']);
    }

    // ──────────── Relations ────────────

    #[Test]
    public function has_many(): void
    {
        $u = TestUser::create(['name' => 'Author', 'email' => 'auth@t.com']);
        TestPost::create(['title' => 'Post 1', 'user_id' => $u->id]);
        TestPost::create(['title' => 'Post 2', 'user_id' => $u->id]);

        $posts = $u->posts()->getResults();
        $this->assertCount(2, $posts);
        $this->assertSame('Post 1', $posts[0]->title);
    }

    #[Test]
    public function belongs_to(): void
    {
        $u = TestUser::create(['name' => 'Owner', 'email' => 'o@t.com']);
        $p = TestPost::create(['title' => 'Belongs', 'user_id' => $u->id]);

        $owner = $p->user()->getResults();
        $this->assertNotNull($owner);
        $this->assertSame('Owner', $owner->name);
    }

    #[Test]
    public function has_one(): void
    {
        $u = TestUser::create(['name' => 'PicUser', 'email' => 'p@t.com']);
        TestAvatar::create(['url' => '/pic.png', 'user_id' => $u->id]);

        $av = $u->avatar()->getResults();
        $this->assertNotNull($av);
        $this->assertSame('/pic.png', $av->url);
    }

    #[Test]
    public function eager_loading(): void
    {
        $u = TestUser::create(['name' => 'EL', 'email' => 'el@t.com']);
        TestPost::create(['title' => 'EL Post', 'user_id' => $u->id]);

        $users = TestUser::query()->with('posts')->get();
        $this->assertCount(1, $users);
        $loaded = $users[0]->posts()->getResults();
        $this->assertCount(1, $loaded);
    }

    #[Test]
    public function eager_loading_belongs_to_uses_column_mapping(): void
    {
        $u = TestUser::create(['name' => 'Parent', 'email' => 'parent@t.com']);
        TestPost::create(['title' => 'Child', 'user_id' => $u->id]);

        $posts = TestPost::query()->with('user')->get();
        $this->assertCount(1, $posts);
        $owner = $posts[0]->user()->getResults();
        $this->assertInstanceOf(TestUser::class, $owner);
        $this->assertSame('Parent', $owner->name);
    }

    #[Test]
    public function eager_loading_has_one_returns_single_model(): void
    {
        $u = TestUser::create(['name' => 'AvatarUser', 'email' => 'avatar@t.com']);
        TestAvatar::create(['url' => '/avatar.png', 'user_id' => $u->id]);

        $users = TestUser::query()->with('avatar')->get();
        $avatar = $users[0]->avatar()->getResults();

        $this->assertInstanceOf(TestAvatar::class, $avatar);
        $this->assertSame('/avatar.png', $avatar->url);
    }

    #[Test]
    public function pagination(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            TestUser::create(['name' => "User{$i}", 'email' => "u{$i}@t.com"]);
        }
        $paginator = TestUser::query()->orderBy('name')->paginate(10);
        $this->assertSame(10, $paginator->perPage);
        $this->assertSame(25, $paginator->total);
        $this->assertSame(3, $paginator->pages);
        $this->assertCount(10, $paginator->items);
    }

    #[Test]
    public function type_casting(): void
    {
        $u = TestUser::create(['name' => 'Cast', 'email' => 'c@t.com']);
        $this->assertIsInt($u->id);
        $this->assertIsString($u->name);
    }

    #[Test]
    public function create_returns_model_with_id(): void
    {
        $u = TestUser::create(['name' => 'Fresh', 'email' => 'fresh@t.com']);
        $this->assertInstanceOf(TestUser::class, $u);
        $this->assertGreaterThan(0, $u->id);
    }

    #[Test]
    public function where_between(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            TestUser::create(['name' => "B{$i}", 'email' => "b{$i}@t.com"]);
        }
        $results = TestUser::query()->whereBetween('id', 2, 4)->get();
        $this->assertCount(3, $results);
    }

    #[Test]
    public function where_in_empty_returns_no_rows(): void
    {
        TestUser::create(['name' => 'A', 'email' => 'a@t.com']);
        $this->assertSame([], TestUser::query()->whereIn('id', [])->get());
    }

    #[Test]
    public function where_not_in_empty_is_noop(): void
    {
        TestUser::create(['name' => 'A', 'email' => 'a@t.com']);
        $this->assertCount(1, TestUser::query()->whereNotIn('id', [])->get());
    }

    #[Test]
    public function invalid_where_operator_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestUser::query()->where('name', 'IS NOT NULL; DROP TABLE users', 'x')->get();
    }

    #[Test]
    public function invalid_order_direction_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestUser::query()->orderBy('name', 'DESC; DROP TABLE users')->get();
    }

    #[Test]
    public function invalid_identifier_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestUser::query()->where('name; DROP TABLE users', 'x')->get();
    }
}
