<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        @php
            $navigationUser = auth()->user();
            $navigationGroups = $navigationUser instanceof \App\Models\User
                ? app(\App\Support\Navigation::class)->groupsFor($navigationUser)
                : [];
        @endphp
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" :href="route(auth()->user()->homeRoute())" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @foreach ($navigationGroups ?? [] as $group)
                    <flux:sidebar.group :heading="$group['heading']" class="grid" wire:key="nav-{{ $group['heading'] }}">
                        @foreach ($group['items'] as $item)
                            <flux:sidebar.item
                                :icon="$item['icon']"
                                :href="route($item['route'])"
                                :current="request()->routeIs($item['route'])"
                                :badge="$item['badge']"
                                wire:navigate
                                wire:key="nav-item-{{ $item['route'] }}"
                            >
                                {{ $item['label'] }}
                            </flux:sidebar.item>
                        @endforeach
                    </flux:sidebar.group>
                @endforeach
            </flux:sidebar.nav>

            <flux:spacer />
        </flux:sidebar>

        <flux:main class="!p-0">
            <header class="sticky top-0 z-10 flex flex-wrap items-center gap-3 border-b border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95">
                <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

                <div class="min-w-0 flex-1">
                    <livewire:global-search />
                </div>

                <flux:button :href="route('notifications.index')" icon="bell" variant="ghost" :badge="auth()->user()->unreadNotifications()->count() ?: null" aria-label="Notifications" />

                <flux:dropdown position="bottom" align="end">
                    <flux:profile
                        class="shrink-0 max-sm:[&>span]:hidden"
                        :name="auth()->user()->name"
                        :initials="auth()->user()->initials()"
                        icon-trailing="chevron-down"
                        data-test="sidebar-menu-button"
                    />

                    <flux:menu>
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar
                                :name="auth()->user()->name"
                                :initials="auth()->user()->initials()"
                            />
                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>

                        <flux:menu.separator />

                        <flux:menu.item :href="route('profile.edit')" icon="user" wire:navigate>
                            {{ __('Account') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('appearance.edit')" icon="swatch" wire:navigate>
                            {{ __('Appearance') }}
                        </flux:menu.item>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item
                                as="button"
                                type="submit"
                                icon="arrow-right-start-on-rectangle"
                                class="w-full cursor-pointer"
                                data-test="logout-button"
                            >
                                {{ __('Log out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>

                <div class="w-full sm:order-first sm:w-48">
                    <livewire:device-switcher />
                </div>
            </header>

            <div class="px-4 py-4 sm:px-6 lg:px-8 lg:py-6">
                {{ $slot }}
            </div>
        </flux:main>

        <livewire:record-drawer />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
