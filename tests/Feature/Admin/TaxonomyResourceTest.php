<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TaxonomyResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'admin',
            'email' => 'admin@lerama.local',
            'password' => Hash::make('strong-password-123'),
        ]));
    }

    public function test_creating_a_category_without_a_slug_transliterates_the_name(): void
    {
        Livewire::test(ManageCategories::class)
            ->callAction('create', ['name' => 'Ciência & Tecnologia', 'slug' => null])
            ->assertHasNoActionErrors();

        $this->assertSame(
            'ciencia-tecnologia',
            Category::where('name', 'Ciência & Tecnologia')->value('slug')
        );
    }

    public function test_a_duplicate_category_slug_is_rejected(): void
    {
        Category::create(['name' => 'Blogs', 'slug' => 'blogs']);

        Livewire::test(ManageCategories::class)
            ->callAction('create', ['name' => 'Outros Blogs', 'slug' => 'blogs'])
            ->assertHasActionErrors(['slug']);

        $this->assertSame(1, Category::where('slug', 'blogs')->count());
    }

    public function test_editing_a_category_keeps_its_own_slug_valid(): void
    {
        $category = Category::create(['name' => 'Blogs', 'slug' => 'blogs']);

        Livewire::test(ManageCategories::class)
            ->callTableAction('edit', $category, ['name' => 'Blogs Pessoais', 'slug' => 'blogs'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Blogs Pessoais', $category->fresh()->name);
        $this->assertSame('blogs', $category->fresh()->slug);
    }

    public function test_the_category_listing_counts_associated_feeds(): void
    {
        [$feed, $category] = $this->seedBasicData();

        Livewire::test(ManageCategories::class)
            ->assertCanSeeTableRecords([$category])
            ->assertTableColumnStateSet('feed_count', 1, $category);
    }

    public function test_creating_a_tag_without_a_slug_transliterates_the_name(): void
    {
        Livewire::test(ManageTags::class)
            ->callAction('create', ['name' => 'Programação', 'slug' => null])
            ->assertHasNoActionErrors();

        $this->assertSame('programacao', Tag::where('name', 'Programação')->value('slug'));
    }

    public function test_deleting_a_tag_leaves_its_feeds_intact(): void
    {
        [$feed, , $tag] = $this->seedBasicData();

        Livewire::test(ManageTags::class)
            ->callTableAction('delete', $tag);

        $this->assertSame(0, Tag::whereKey($tag->id)->count());
        // The pivot cascade must not take the feed with it.
        $this->assertSame(1, Feed::whereKey($feed->id)->count());
    }
}
