<?php

namespace App\Livewire;

use App\Enums\CategoryType;
use App\Enums\MailAccountStatus;
use App\Http\Middleware\SetLocale;
use App\Livewire\Actions\Logout;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Settings extends Component
{
    public string $newName = '';

    public string $newIcon = '';

    public string $newType = 'expense';

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

        session()->flash('status', __('Đã ngắt kết nối Gmail và xóa mật khẩu ứng dụng. Giao dịch cũ vẫn được giữ.'));
    }

    public function addCategory(): void
    {
        $validated = $this->validate([
            'newName' => ['required', 'string', 'max:50'],
            'newIcon' => ['nullable', 'string', 'max:16'],
            'newType' => ['required', Rule::enum(CategoryType::class)],
        ], [], ['newName' => __('tên danh mục')]);

        auth()->user()->categories()->create([
            'name' => $validated['newName'],
            'icon' => $validated['newIcon'] ?: null,
            'type' => $validated['newType'],
        ]);

        $this->reset('newName', 'newIcon', 'newType');
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
        ], [], ['editingName' => __('tên danh mục')]);

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
        if (! in_array($locale, SetLocale::LOCALES, true)) {
            return;
        }

        session()->put('locale', $locale);

        $this->redirectRoute('settings');
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/');
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.settings', [
            'account' => $user->mailAccount,
            'categories' => $user->categories()->withCount('transactions')->orderBy('type')->orderBy('id')->get(),
            'rules' => $user->categoryRules()->with('category')->latest()->get(),
        ])->title(__('Cài đặt'));
    }
}
