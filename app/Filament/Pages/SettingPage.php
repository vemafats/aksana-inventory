<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SettingPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Setting';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Setting';

    protected static ?string $slug = 'setting';

    protected static string $view = 'filament.pages.setting';

    public string $activeTab = 'users';

    public string $userSearch = '';

    public ?string $selectedUserId = null;

    public string $editName = '';

    public string $editEmail = '';

    public string $editUsername = '';

    public string $editRole = 'admin';

    public string $editPassword = '';

    public bool $editIsActive = true;

    public bool $isCreating = false;

    public ?string $editingRoleKey = null;

    public string $editRoleDescription = '';

    public string $selectedRole = 'owner';

    /** @var array<string, array<string, array<string, bool>>> */
    public array $permissions = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canManageSettings();
    }

    public function mount(): void
    {
        $this->loadPermissions();
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['users', 'roles', 'menu_access'], true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->resetForm();
        $this->editingRoleKey = null;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return User::query()
            ->when($this->userSearch !== '', function ($query): void {
                $search = '%'.$this->userSearch.'%';
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function getUserCount(): int
    {
        return User::query()->count();
    }

    public function usernameFor(User $user): string
    {
        $local = Str::before($user->email, '@');

        return $local !== '' ? $local : Str::slug($user->name, '.');
    }

    public function userCode(User $user): string
    {
        return 'USR-'.Str::upper(Str::substr(str_replace('-', '', $user->id), 0, 3));
    }

    public function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if (count($parts) >= 2) {
            return Str::upper(Str::substr($parts[0], 0, 1).Str::substr($parts[1], 0, 1));
        }

        return Str::upper(Str::substr($name, 0, 2));
    }

    public function lastLoginLabel(User $user): string
    {
        return $user->updated_at?->diffForHumans() ?? '—';
    }

    public function roleBadgeLabel(UserRole $role): string
    {
        return match ($role) {
            UserRole::OWNER => 'OWNER',
            UserRole::ADMIN => 'ADMIN',
            UserRole::ADMIN_GUDANG => 'GUDANG',
            UserRole::PIC_BAZAR => 'PIC BAZAR',
            UserRole::SALES => 'SALES',
        };
    }

    public function roleBadgeStyle(UserRole $role): string
    {
        return match ($role) {
            UserRole::OWNER => 'background:#070D1E;color:#fff',
            UserRole::ADMIN => 'background:#1660ED;color:#fff',
            UserRole::ADMIN_GUDANG => 'background:#0F6E56;color:#fff',
            UserRole::PIC_BAZAR => 'background:#29A85A;color:#fff',
            UserRole::SALES => 'background:#49586B;color:#fff',
        };
    }

    public function selectUser(string $id): void
    {
        $user = User::query()->findOrFail($id);
        $this->selectedUserId = $user->id;
        $this->isCreating = false;
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        $this->editUsername = $this->usernameFor($user);
        $this->editRole = $user->role->value;
        $this->editPassword = '';
        $this->editIsActive = $user->is_active;
    }

    public function openCreateUser(): void
    {
        $this->resetForm();
        $this->isCreating = true;
    }

    public function resetForm(): void
    {
        $this->selectedUserId = null;
        $this->isCreating = false;
        $this->editName = '';
        $this->editEmail = '';
        $this->editUsername = '';
        $this->editRole = UserRole::ADMIN->value;
        $this->editPassword = '';
        $this->editIsActive = true;
    }

    public function saveUser(): void
    {
        $rules = [
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['required', 'email', 'max:255'],
            'editUsername' => ['required', 'string', 'max:255'],
            'editRole' => ['required', Rule::in(UserRole::values())],
            'editIsActive' => ['boolean'],
        ];

        if ($this->isCreating) {
            $rules['editPassword'] = ['required', Password::min(8)];
            $rules['editEmail'][] = 'unique:users,email';
        } else {
            $rules['editPassword'] = ['nullable', Password::min(8)];
            $rules['editEmail'][] = Rule::unique('users', 'email')->ignore($this->selectedUserId);
        }

        $this->validate($rules);

        $email = $this->editEmail;
        if (! str_contains($email, '@')) {
            $email = $this->editUsername.'@aksana.id';
        }

        $data = [
            'name' => $this->editName,
            'email' => $email,
            'role' => $this->editRole,
            'is_active' => $this->editIsActive,
        ];

        if ($this->editPassword !== '') {
            $data['password'] = Hash::make($this->editPassword);
        }

        if ($this->isCreating) {
            User::query()->create([
                'id' => (string) Str::uuid(),
                ...$data,
                'password' => $data['password'] ?? Hash::make($this->editPassword),
            ]);

            Notification::make()->title('User berhasil ditambahkan')->success()->send();
        } else {
            $user = User::query()->findOrFail($this->selectedUserId);
            $user->update($data);

            Notification::make()->title('User berhasil diperbarui')->success()->send();
        }

        $this->resetForm();
    }

    public function deleteUser(string $id): void
    {
        if ($id === auth()->id()) {
            Notification::make()->title('Tidak dapat menghapus akun sendiri')->danger()->send();

            return;
        }

        User::query()->findOrFail($id)->delete();

        if ($this->selectedUserId === $id) {
            $this->resetForm();
        }

        Notification::make()->title('User berhasil dihapus')->success()->send();
    }

    /**
     * @return array<string, array{label: string, description: string, badge: string}>
     */
    public function getRoleDefinitions(): array
    {
        $descriptions = $this->getRoleDescriptions();

        return [
            'owner' => [
                'label' => 'Owner',
                'description' => $descriptions['owner'] ?? 'Akses penuh ke seluruh modul & pengaturan sistem.',
                'badge' => 'OWNER',
            ],
            'admin' => [
                'label' => 'Admin',
                'description' => $descriptions['admin'] ?? 'Kelola master data, katalog, stok & laporan.',
                'badge' => 'ADMIN',
            ],
            'admin_gudang' => [
                'label' => 'Admin Gudang',
                'description' => $descriptions['admin_gudang'] ?? 'Kelola stok masuk, distribusi & stok opname.',
                'badge' => 'GUDANG',
            ],
            'pic_bazar' => [
                'label' => 'PIC Bazar',
                'description' => $descriptions['pic_bazar'] ?? 'Kelola penjualan harian & cek stok cabang.',
                'badge' => 'PIC BAZAR',
            ],
            'sales' => [
                'label' => 'Sales',
                'description' => $descriptions['sales'] ?? 'Input transaksi jual & cek stok lokasi.',
                'badge' => 'SALES',
            ],
        ];
    }

    public function roleUserCount(string $role): int
    {
        return User::query()->where('role', $role)->count();
    }

    public function editRoleCard(string $roleKey): void
    {
        $this->editingRoleKey = $roleKey;
        $descriptions = $this->getRoleDescriptions();
        $this->editRoleDescription = $descriptions[$roleKey] ?? $this->getRoleDefinitions()[$roleKey]['description'] ?? '';
    }

    public function saveRoleDescription(): void
    {
        if ($this->editingRoleKey === null) {
            return;
        }

        $descriptions = $this->getRoleDescriptions();
        $descriptions[$this->editingRoleKey] = $this->editRoleDescription;
        $this->persistSetting('role_descriptions', json_encode($descriptions));

        $this->editingRoleKey = null;
        $this->editRoleDescription = '';

        Notification::make()->title('Deskripsi role disimpan')->success()->send();
    }

    public function cancelRoleEdit(): void
    {
        $this->editingRoleKey = null;
        $this->editRoleDescription = '';
    }

    public function notifyRoleComingSoon(): void
    {
        Notification::make()
            ->title('Role baru akan tersedia di versi berikutnya.')
            ->info()
            ->send();
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    public function getMenuDefinitions(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'icon' => 'heroicon-o-home'],
            'master_data' => ['label' => 'Master Data', 'icon' => 'heroicon-o-squares-2x2'],
            'katalog' => ['label' => 'Katalog', 'icon' => 'heroicon-o-shopping-bag'],
            'stok' => ['label' => 'Stok', 'icon' => 'heroicon-o-archive-box'],
            'distribusi' => ['label' => 'Distribusi', 'icon' => 'heroicon-o-arrow-right-circle'],
            'penjualan' => ['label' => 'Penjualan', 'icon' => 'heroicon-o-shopping-cart'],
            'stok_opname' => ['label' => 'Stok Opname', 'icon' => 'heroicon-o-clipboard-document-check'],
            'laporan' => ['label' => 'Laporan', 'icon' => 'heroicon-o-chart-bar'],
            'setting' => ['label' => 'Setting', 'icon' => 'heroicon-o-cog-6-tooth'],
        ];
    }

    /**
     * @return list<string>
     */
    public function getPermissionActions(): array
    {
        return ['view', 'create', 'edit', 'delete'];
    }

    public function selectPermissionRole(string $role): void
    {
        if (! in_array($role, UserRole::values(), true)) {
            return;
        }

        $this->selectedRole = $role;
        $this->loadPermissions();
    }

    public function loadPermissions(): void
    {
        $stored = Setting::get('role_permissions');
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        $this->permissions = is_array($decoded) && $decoded !== []
            ? $this->mergeWithDefaultPermissions($decoded)
            : $this->defaultPermissions();
    }

    public function togglePermission(string $menu, string $action): void
    {
        $current = $this->permissions[$this->selectedRole][$menu][$action] ?? false;
        $this->permissions[$this->selectedRole][$menu][$action] = ! $current;
    }

    public function savePermissions(): void
    {
        $this->persistSetting('role_permissions', json_encode($this->permissions));

        Notification::make()->title('Hak akses menu disimpan')->success()->send();
    }

    public function isPermissionChecked(string $menu, string $action): bool
    {
        return (bool) ($this->permissions[$this->selectedRole][$menu][$action] ?? false);
    }

    /**
     * @return array<string, string>
     */
    protected function getRoleDescriptions(): array
    {
        $stored = Setting::get('role_descriptions');
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        return is_array($decoded) ? $decoded : $this->defaultRoleDescriptions();
    }

    /**
     * @param  array<string, array<string, array<string, bool>>>  $stored
     * @return array<string, array<string, array<string, bool>>>
     */
    protected function mergeWithDefaultPermissions(array $stored): array
    {
        $defaults = $this->defaultPermissions();

        foreach ($defaults as $role => $menus) {
            foreach ($menus as $menu => $actions) {
                foreach ($actions as $action => $value) {
                    if (! isset($stored[$role][$menu][$action])) {
                        $stored[$role][$menu][$action] = $value;
                    }
                }
            }
        }

        return $stored;
    }

    /**
     * @return array<string, array<string, array<string, bool>>>
     */
    protected function defaultPermissions(): array
    {
        $none = ['view' => false, 'create' => false, 'edit' => false, 'delete' => false];
        $view = ['view' => true, 'create' => false, 'edit' => false, 'delete' => false];
        $viewCreate = ['view' => true, 'create' => true, 'edit' => false, 'delete' => false];
        $viewCreateEdit = ['view' => true, 'create' => true, 'edit' => true, 'delete' => false];
        $full = ['view' => true, 'create' => true, 'edit' => true, 'delete' => true];

        $allMenus = array_keys($this->getMenuDefinitions());
        $ownerPerms = [];
        foreach ($allMenus as $menu) {
            $ownerPerms[$menu] = $full;
        }

        $adminPerms = $ownerPerms;
        $adminPerms['setting']['delete'] = false;

        $gudangPerms = array_fill_keys($allMenus, $none);
        $gudangPerms['dashboard'] = $view;
        $gudangPerms['katalog'] = $view;
        $gudangPerms['stok'] = $viewCreateEdit;
        $gudangPerms['distribusi'] = $viewCreateEdit;
        $gudangPerms['stok_opname'] = $viewCreateEdit;

        $picPerms = array_fill_keys($allMenus, $none);
        $picPerms['katalog'] = $view;
        $picPerms['stok'] = $view;
        $picPerms['distribusi'] = $view;
        $picPerms['penjualan'] = $viewCreate;
        $picPerms['stok_opname'] = $viewCreate;

        $salesPerms = array_fill_keys($allMenus, $none);
        $salesPerms['katalog'] = $view;
        $salesPerms['stok'] = $view;
        $salesPerms['penjualan'] = $viewCreate;

        return [
            'owner' => $ownerPerms,
            'admin' => $adminPerms,
            'admin_gudang' => $gudangPerms,
            'pic_bazar' => $picPerms,
            'sales' => $salesPerms,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function defaultRoleDescriptions(): array
    {
        return [
            'owner' => 'Akses penuh ke seluruh modul & pengaturan sistem.',
            'admin' => 'Kelola master data, katalog, stok & laporan.',
            'admin_gudang' => 'Kelola stok masuk, distribusi & stok opname.',
            'pic_bazar' => 'Kelola penjualan harian & cek stok cabang.',
            'sales' => 'Input transaksi jual & cek stok lokasi.',
        ];
    }

    protected function persistSetting(string $key, string $value): void
    {
        $setting = Setting::query()->firstOrNew(['setting_key' => $key]);

        if (! $setting->exists) {
            $setting->id = (string) Str::uuid();
        }

        $setting->setting_value = $value;
        $setting->save();
    }
}
