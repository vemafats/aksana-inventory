<x-filament-panels::page>
    {{-- Tab bar --}}
    <div class="mb-6 flex flex-wrap gap-2 border-b border-[var(--aksana-border)] bg-white px-1 pb-0 pt-1 rounded-t-lg">
        @foreach ([
            'users' => ['label' => 'Users', 'icon' => 'heroicon-o-user'],
            'roles' => ['label' => 'Roles', 'icon' => 'heroicon-o-shield-check'],
            'menu_access' => ['label' => 'Menu Access', 'icon' => 'heroicon-o-key'],
        ] as $key => $tab)
            <button
                type="button"
                wire:click="setActiveTab('{{ $key }}')"
                @class([
                    'aksana-tab mb-[-1px]',
                    'aksana-tab-active' => $activeTab === $key,
                    'border border-[var(--aksana-border)] border-b-white bg-white text-[var(--aksana-muted)]' => $activeTab !== $key,
                ])
            >
                <x-dynamic-component :component="$tab['icon']" class="h-4 w-4" />
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    {{-- TAB 1: USERS --}}
    @if ($activeTab === 'users')
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- Left: user list --}}
            <div class="lg:col-span-2 aksana-panel !p-0 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--aksana-border)] px-4 py-3">
                    <h2 class="text-sm font-semibold text-[var(--aksana-void)]">
                        Daftar User · {{ $this->getUserCount() }} akun
                    </h2>
                    <div class="flex items-center gap-2">
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="userSearch"
                            placeholder="Cari user..."
                            class="rounded-md border border-[var(--aksana-border)] px-3 py-1.5 text-sm w-40"
                        />
                        <button type="button" wire:click="openCreateUser" class="aksana-tab aksana-tab-active text-[10px] whitespace-nowrap">
                            + USER
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="aksana-table w-full text-sm px-4">
                        <thead>
                            <tr>
                                <th class="pl-4">User</th>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th class="pr-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->getUsers() as $user)
                                <tr wire:key="user-{{ $user->id }}" class="hover:bg-gray-50/80">
                                    <td class="pl-4">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white" style="background:#070D1E">
                                            {{ $this->initials($user->name) }}
                                        </div>
                                    </td>
                                    <td class="aksana-mono text-[var(--aksana-muted)] text-xs">{{ $this->usernameFor($user) }}</td>
                                    <td class="font-semibold">{{ $user->name }}</td>
                                    <td>
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide" style="{{ $this->roleBadgeStyle($user->role) }}">
                                            {{ $this->roleBadgeLabel($user->role) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($user->is_active)
                                            <span class="inline-flex items-center gap-1.5 text-xs text-green-600">
                                                <span class="h-2 w-2 rounded-full bg-green-500"></span> Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-xs text-[var(--aksana-muted)]">
                                                <span class="h-2 w-2 rounded-full bg-gray-400"></span> Nonaktif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-xs text-[var(--aksana-muted)]">{{ $this->lastLoginLabel($user) }}</td>
                                    <td class="pr-4">
                                        <div class="flex gap-1">
                                            <button type="button" wire:click="selectUser('{{ $user->id }}')" class="flex h-7 w-7 items-center justify-center rounded-md border border-[var(--aksana-border)] bg-gray-50 hover:bg-gray-100" title="Edit">
                                                <x-heroicon-o-pencil class="h-3.5 w-3.5 text-[var(--aksana-muted)]" />
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="deleteUser('{{ $user->id }}')"
                                                wire:confirm="Hapus user {{ $user->name }}?"
                                                class="flex h-7 w-7 items-center justify-center rounded-md border border-[var(--aksana-border)] bg-gray-50 hover:border-red-300 hover:text-red-600"
                                                title="Hapus"
                                            >
                                                <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Right: edit panel --}}
            <div class="aksana-panel">
                @if ($isCreating || $selectedUserId)
                    <h2 class="aksana-panel-title mb-4">{{ $isCreating ? 'Tambah User' : 'Edit User' }}</h2>

                    @if (! $isCreating && $selectedUserId)
                        @php $selectedUser = \App\Models\User::find($selectedUserId); @endphp
                        @if ($selectedUser)
                            <div class="mb-6 flex flex-col items-center text-center">
                                <div class="mb-2 flex h-12 w-12 items-center justify-center rounded-full text-sm font-bold text-white" style="background:#070D1E">
                                    {{ $this->initials($selectedUser->name) }}
                                </div>
                                <p class="font-bold text-[var(--aksana-void)]">{{ $selectedUser->name }}</p>
                                <p class="text-xs font-mono text-[var(--aksana-muted)]">{{ $this->userCode($selectedUser) }}</p>
                            </div>
                        @endif
                    @endif

                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-[var(--aksana-muted)]">Username</label>
                            <div class="relative">
                                <x-heroicon-o-user class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--aksana-muted)]" />
                                <input type="text" wire:model="editUsername" class="w-full rounded-md border border-[var(--aksana-border)] py-2 pl-9 pr-3 text-sm" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-[var(--aksana-muted)]">Email</label>
                            <div class="relative">
                                <x-heroicon-o-envelope class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--aksana-muted)]" />
                                <input type="email" wire:model="editEmail" class="w-full rounded-md border border-[var(--aksana-border)] py-2 pl-9 pr-3 text-sm" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-[var(--aksana-muted)]">Nama Lengkap</label>
                            <input type="text" wire:model="editName" class="w-full rounded-md border border-[var(--aksana-border)] px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-[var(--aksana-muted)]">Role</label>
                            <select wire:model="editRole" class="w-full rounded-md border border-[var(--aksana-border)] px-3 py-2 text-sm">
                                @foreach (\App\Enums\UserRole::cases() as $role)
                                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-[var(--aksana-muted)]">Password Baru</label>
                            <div class="relative">
                                <x-heroicon-o-lock-closed class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--aksana-muted)]" />
                                <input type="password" wire:model="editPassword" placeholder="Kosongkan jika tidak ingin mengubah password" class="w-full rounded-md border border-[var(--aksana-border)] py-2 pl-9 pr-3 text-sm" />
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase text-[var(--aksana-muted)]">Status Akun</span>
                            <label class="inline-flex cursor-pointer items-center gap-2">
                                <input type="checkbox" wire:model.live="editIsActive" class="rounded" />
                                <span class="text-xs font-bold {{ $editIsActive ? 'text-green-600' : 'text-[var(--aksana-muted)]' }}">
                                    {{ $editIsActive ? 'AKTIF' : 'NONAKTIF' }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 space-y-2">
                        <button type="button" wire:click="saveUser" class="aksana-tab aksana-tab-active w-full justify-center py-2.5 text-[11px]">
                            SIMPAN PERUBAHAN
                        </button>
                        <button type="button" wire:click="resetForm" class="aksana-tab w-full justify-center border border-[var(--aksana-border)] bg-white py-2.5 text-[11px]">
                            RESET
                        </button>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-12 text-center text-[var(--aksana-muted)]">
                        <x-heroicon-o-user class="mb-3 h-10 w-10 opacity-40" />
                        <p class="text-sm">Pilih user untuk diedit atau klik <strong>+ USER</strong></p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- TAB 2: ROLES --}}
    @if ($activeTab === 'roles')
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-[var(--aksana-void)]">Role · 5 role</h2>
            <button type="button" wire:click="notifyRoleComingSoon" class="aksana-tab aksana-tab-active text-[10px]">+ ROLE</button>
        </div>

        @if ($editingRoleKey)
            <div class="aksana-panel mb-4">
                <h3 class="mb-3 text-sm font-semibold">Edit Deskripsi — {{ $this->getRoleDefinitions()[$editingRoleKey]['label'] ?? $editingRoleKey }}</h3>
                <textarea wire:model="editRoleDescription" rows="3" class="mb-3 w-full rounded-md border border-[var(--aksana-border)] px-3 py-2 text-sm"></textarea>
                <div class="flex gap-2">
                    <button type="button" wire:click="saveRoleDescription" class="aksana-tab aksana-tab-active text-[10px]">Simpan</button>
                    <button type="button" wire:click="cancelRoleEdit" class="aksana-tab border border-[var(--aksana-border)] bg-white text-[10px]">Batal</button>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach ($this->getRoleDefinitions() as $roleKey => $role)
                <div class="aksana-panel relative" wire:key="role-{{ $roleKey }}">
                    <div class="absolute right-3 top-3 flex gap-1">
                        <button type="button" wire:click="editRoleCard('{{ $roleKey }}')" class="flex h-7 w-7 items-center justify-center rounded-md border border-[var(--aksana-border)] bg-gray-50 hover:bg-gray-100">
                            <x-heroicon-o-pencil class="h-3.5 w-3.5 text-[var(--aksana-muted)]" />
                        </button>
                        <button type="button" wire:click="notifyRoleComingSoon" class="flex h-7 w-7 items-center justify-center rounded-md border border-[var(--aksana-border)] bg-gray-50 hover:border-red-300">
                            <x-heroicon-o-trash class="h-3.5 w-3.5 text-[var(--aksana-muted)]" />
                        </button>
                    </div>
                    <div class="mb-3 flex items-start gap-3 pr-16">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100">
                            <x-heroicon-o-shield-check class="h-4 w-4 text-[var(--aksana-muted)]" />
                        </div>
                        <div>
                            <p class="font-bold text-[var(--aksana-void)]">{{ $role['label'] }}</p>
                            <p class="text-xs text-[var(--aksana-muted)]">{{ $this->roleUserCount($roleKey) }} user</p>
                        </div>
                    </div>
                    <p class="text-sm text-[var(--aksana-muted)]">{{ $role['description'] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- TAB 3: MENU ACCESS --}}
    @if ($activeTab === 'menu_access')
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[240px_1fr]">
            <aside class="aksana-panel !p-2">
                <p class="mb-2 px-2 text-[10px] font-bold uppercase tracking-wider text-[var(--aksana-muted)]">Pilih Role</p>
                <nav class="flex flex-col gap-1">
                    @foreach ($this->getRoleDefinitions() as $roleKey => $role)
                        <button
                            type="button"
                            wire:click="selectPermissionRole('{{ $roleKey }}')"
                            @class([
                                'flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-xs font-semibold transition',
                                'bg-[var(--aksana-void)] text-white' => $selectedRole === $roleKey,
                                'text-[var(--aksana-void)] hover:bg-gray-100' => $selectedRole !== $roleKey,
                            ])
                        >
                            <x-heroicon-o-shield-check @class(['h-3.5 w-3.5 shrink-0', 'text-white' => $selectedRole === $roleKey]) />
                            <span class="flex-1">{{ $role['label'] }}</span>
                            <span @class(['font-mono text-[11px]', 'text-white/70' => $selectedRole === $roleKey, 'text-[var(--aksana-muted)]' => $selectedRole !== $roleKey])>
                                {{ str_pad((string) $this->roleUserCount($roleKey), 2, '0', STR_PAD_LEFT) }}
                            </span>
                        </button>
                    @endforeach
                </nav>
            </aside>

            <section class="aksana-panel !p-0 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--aksana-border)] px-4 py-3">
                    <h2 class="text-sm font-semibold text-[var(--aksana-void)]">
                        Hak Akses Menu — {{ $this->getRoleDefinitions()[$selectedRole]['label'] ?? $selectedRole }}
                    </h2>
                    <button type="button" wire:click="savePermissions" class="aksana-tab aksana-tab-active text-[10px]">SIMPAN</button>
                </div>

                <div class="overflow-x-auto p-4">
                    <table class="aksana-table w-full text-sm">
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <th class="text-center">View</th>
                                <th class="text-center">Create</th>
                                <th class="text-center">Edit</th>
                                <th class="text-center">Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->getMenuDefinitions() as $menuKey => $menu)
                                <tr wire:key="perm-{{ $selectedRole }}-{{ $menuKey }}" class="hover:bg-gray-50/80">
                                    <td>
                                        <div class="flex items-center gap-2 font-semibold">
                                            <x-dynamic-component :component="$menu['icon']" class="h-4 w-4 text-[var(--aksana-muted)]" />
                                            {{ $menu['label'] }}
                                        </div>
                                    </td>
                                    @foreach ($this->getPermissionActions() as $action)
                                        <td class="text-center">
                                            <button
                                                type="button"
                                                wire:click="togglePermission('{{ $menuKey }}', '{{ $action }}')"
                                                @class([
                                                    'mx-auto flex h-5 w-5 items-center justify-center rounded border transition',
                                                    'border-[var(--aksana-void)] bg-[var(--aksana-void)] text-white' => $this->isPermissionChecked($menuKey, $action),
                                                    'border-gray-300 bg-white' => ! $this->isPermissionChecked($menuKey, $action),
                                                ])
                                            >
                                                @if ($this->isPermissionChecked($menuKey, $action))
                                                    <x-heroicon-m-check class="h-3 w-3" />
                                                @endif
                                            </button>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[var(--aksana-border)] px-4 py-3 text-xs text-[var(--aksana-muted)]">
                    <span>Centang untuk mengizinkan akses · klik SIMPAN untuk menerapkan.</span>
                    <span class="font-mono">9 menu × 4 permission</span>
                </div>
            </section>
        </div>
    @endif
</x-filament-panels::page>
