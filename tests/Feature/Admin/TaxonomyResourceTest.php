<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Category;
use App\Models\Feed;
use App\Models\Tag;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\AdminUsers;

beforeEach(function () {
    $this->actingAs(AdminUsers::admin());
});

it('transliterates the name when creating a category without a slug', function () {
    Livewire::test(ManageCategories::class)
        ->callAction('create', ['name' => 'Ciência & Tecnologia', 'slug' => null])
        ->assertHasNoFormErrors();

    expect(Category::where('name', 'Ciência & Tecnologia')->value('slug'))
        ->toBe('ciencia-tecnologia');
});

it('rejects a duplicate category slug', function () {
    Category::create(['name' => 'Blogs', 'slug' => 'blogs']);

    Livewire::test(ManageCategories::class)
        ->callAction('create', ['name' => 'Outros Blogs', 'slug' => 'blogs'])
        ->assertHasFormErrors(['slug']);

    expect(Category::where('slug', 'blogs')->count())->toBe(1);
});

it('keeps a category own slug valid when editing', function () {
    $category = Category::create(['name' => 'Blogs', 'slug' => 'blogs']);

    Livewire::test(ManageCategories::class)
        ->callAction(TestAction::make('edit')->table($category), ['name' => 'Blogs Pessoais', 'slug' => 'blogs'])
        ->assertHasNoFormErrors();

    expect($category->fresh()->name)->toBe('Blogs Pessoais')
        ->and($category->fresh()->slug)->toBe('blogs');
});

it('counts associated feeds on the category listing', function () {
    [$feed, $category] = $this->seedBasicData();

    Livewire::test(ManageCategories::class)
        ->assertCanSeeTableRecords([$category])
        ->assertTableColumnStateSet('feed_count', 1, $category);
});

it('transliterates the name when creating a tag without a slug', function () {
    Livewire::test(ManageTags::class)
        ->callAction('create', ['name' => 'Programação', 'slug' => null])
        ->assertHasNoFormErrors();

    expect(Tag::where('name', 'Programação')->value('slug'))->toBe('programacao');
});

it('leaves the feeds intact when deleting a tag', function () {
    [$feed, , $tag] = $this->seedBasicData();

    Livewire::test(ManageTags::class)
        ->callAction(TestAction::make('delete')->table($tag));

    expect(Tag::whereKey($tag->id)->count())->toBe(0)
        ->and(Feed::whereKey($feed->id)->count())->toBe(1);
});
