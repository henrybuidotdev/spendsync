<?php

namespace App\Livewire;

use App\Enums\CategoryType;
use App\Enums\Locale;
use App\Enums\MailAccountStatus;
use App\Enums\Theme;
use App\Livewire\Actions\Logout;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class Settings extends Component
{
    public const SECTIONS = ['gmail', 'categories', 'rules', 'appearance'];

    /** Null shows the settings menu; otherwise one of SECTIONS (from the route). */
    #[Locked]
    public ?string $section = null;

    public string $newName = '';

    public string $newIcon = '';

    /** Which kind of category the list shows and new categories get. */
    public string $categoryType = 'expense';

    public ?int $editingId = null;

    public string $editingName = '';

    public string $editingIcon = '';

    public function disconnect(): void
    {
        $account = auth()->user()->mailAccount;

        if ($account === null) {
            return;
        }

        $this->authorize('update', $account);

        $account->update(['app_password' => null, 'status' => MailAccountStatus::Disabled, 'last_error' => null]);

        session()->flash('status', __('Gmail disconnected and app password deleted. Your transactions are kept.'));
    }

    public function showCategoryType(string $type): void
    {
        $this->categoryType = CategoryType::tryFrom($type)?->value ?? CategoryType::Expense->value;
        $this->reset('editingId', 'editingName', 'editingIcon');
    }

    public function addCategory(): void
    {
        $validated = $this->validate([
            'newName' => ['required', 'string', 'max:50'],
            'newIcon' => ['nullable', 'string', 'max:16'],
            'categoryType' => ['required', Rule::enum(CategoryType::class)],
        ], [], ['newName' => __('category name')]);

        auth()->user()->categories()->create([
            'name' => $validated['newName'],
            'icon' => $validated['newIcon'] ?: null,
            'type' => $validated['categoryType'],
            'order' => 1,
        ]);

        $this->reset('newName', 'newIcon');
    }

    public function editCategory(int $id): void
    {
        $category = auth()->user()->categories()->findOrFail($id);

        $this->editingId = $category->id;
        $this->editingName = $category->name;
        $this->editingIcon = (string) $category->icon;
    }

    public function renameCategory(): void
    {
        $this->validate([
            'editingName' => ['required', 'string', 'max:50'],
            'editingIcon' => ['nullable', 'string', 'max:16'],
        ], [], ['editingName' => __('category name')]);

        $category = auth()->user()->categories()->findOrFail($this->editingId);
        $this->authorize('update', $category);

        $category->update(['name' => $this->editingName, 'icon' => $this->editingIcon ?: null]);

        $this->reset('editingId', 'editingName', 'editingIcon');
    }

    public function deleteCategory(int $id): void
    {
        $category = auth()->user()->categories()->findOrFail($id);
        $this->authorize('delete', $category);

        $category->delete();
    }

    public function deleteRule(int $id): void
    {
        $rule = auth()->user()->categoryRules()->findOrFail($id);
        $this->authorize('delete', $rule);

        $rule->delete();
    }

    public function setLocale(string $locale): void
    {
        $locale = Locale::tryFrom($locale);

        if ($locale === null) {
            return;
        }

        $this->saveSettings(['locale' => $locale]);

        $this->redirectRoute('settings', 'appearance');
    }

    public function setTheme(string $theme): void
    {
        $theme = Theme::tryFrom($theme);

        if ($theme === null) {
            return;
        }

        $this->saveSettings(['theme' => $theme]);
    }

    /** @param array<string, Locale|Theme> $values */
    private function saveSettings(array $values): void
    {
        auth()->user()->settings()->updateOrCreate([], $values);
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/');
    }

    public function render()
    {
        $user = auth()->user();
        $title = $this->sectionTitle();

        return view('livewire.settings', [
            'title' => $title,
            'account' => $user->mailAccount,
            'categoryCount' => $this->section === null ? $user->availableCategories()->count() : null,
            'ruleCount' => $this->section === null ? $user->categoryRules()->count() : null,
            'categories' => $this->section !== 'categories' ? collect() : $user->availableCategories()
                ->where('type', $this->categoryType)
                ->withCount(['transactions' => fn ($query) => $query->where('user_id', $user->id)])
                ->ordered()
                ->get(),
            'rules' => $this->section !== 'rules' ? collect() : $user->categoryRules()->with('category')->latest()->get(),
        ])->title($title);
    }

    private function sectionTitle(): string
    {
        return match ($this->section) {
            'gmail' => __('Gmail account'),
            'categories' => __('Category'),
            'rules' => __('Auto-categorize rules'),
            'appearance' => __('Appearance & language'),
            default => __('Settings'),
        };
    }
}
