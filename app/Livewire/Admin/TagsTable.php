<?php

namespace App\Livewire\Admin;

use App\Models\Tag;
use App\Support\Slugger;
use Livewire\Component;

class TagsTable extends Component
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
        $tag = Tag::query()->findOrFail($id);

        $this->editingId = $tag->id;
        $this->name = $tag->name;
        $this->slug = $tag->slug;
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

        $slugExists = Tag::query()
            ->where('slug', $slug)
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();

        if ($slugExists) {
            $this->addError('slug', __('validation.slug_exists'));

            return;
        }

        Tag::updateOrCreate(
            ['id' => $this->editingId],
            ['name' => $this->name, 'slug' => $slug]
        );

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function deleteTag(int $id): void
    {
        Tag::query()->whereKey($id)->delete();
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
        $tags = Tag::query()
            ->select('tags.*')
            ->selectSub(
                'SELECT COUNT(DISTINCT feed_id) FROM feed_tags WHERE tag_id = tags.id',
                'feed_count'
            )
            ->orderBy('tags.name')
            ->get();

        return view('livewire.admin.tags-table', [
            'tags' => $tags,
        ]);
    }
}
