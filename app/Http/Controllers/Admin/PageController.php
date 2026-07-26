<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

// Thin shells around the Livewire tables/forms that do the actual work.
// Controllers rather than closures so the route table stays cacheable.
class PageController extends Controller
{
    public function items(): View
    {
        return view('admin.items');
    }

    public function feeds(): View
    {
        return view('admin.feeds');
    }

    public function createFeed(): View
    {
        return view('admin.feed-form');
    }

    public function editFeed(int $id): View
    {
        return view('admin.feed-form', ['feedId' => $id]);
    }

    public function categories(): View
    {
        return view('admin.categories');
    }

    public function tags(): View
    {
        return view('admin.tags');
    }
}
