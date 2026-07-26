<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Support\Slugger;
use Livewire\Component;

class CategoriesTable extends Component
{
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $slug = '';

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $category = Category::query()->findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $slug = trim($this->slug) !== '' ? trim($this->slug) : $this->generateSlug($this->name);

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100'],
        ], [
            'name.required' => __('validation.name_required'),
        ]);

        $slugExists = Category::query()
            ->where('slug', $slug)
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();

        if ($slugExists) {
            $this->addError('slug', __('validation.slug_exists'));

            return;
        }

        Category::updateOrCreate(
            ['id' => $this->editingId],
            ['name' => $this->name, 'slug' => $slug]
        );

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function deleteCategory(int $id): void
    {
        Category::query()->whereKey($id)->delete(); // pivots go via FK cascade
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->slug = '';
        $this->resetValidation();
    }

    private function generateSlug(string $name): string
    {
        return Slugger::slug($name);
    }

    public function render()
    {
        $categories = Category::query()
            ->select('categories.*')
            ->selectSub(
                'SELECT COUNT(DISTINCT feed_id) FROM feed_categories WHERE category_id = categories.id',
                'feed_count'
            )
            ->orderBy('categories.name')
            ->get();

        return view('livewire.admin.categories-table', [
            'categories' => $categories,
        ]);
    }
}
