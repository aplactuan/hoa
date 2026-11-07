<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('partials.head', ['title' => 'Green Meadows Phase 1 HOA'])
        <meta name="description" content="The Green Meadows Phase 1 HOA portal for property records, dues, requests, and community operations.">
    </head>
    <body class="min-h-screen bg-[#f8faf7] font-sans text-zinc-900 antialiased">
        <div class="border-b border-zinc-200">
            <header class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-5 py-5 sm:px-8 lg:px-10">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3" aria-label="Green Meadows Phase 1 home">
                    <img src="{{ asset('logo.svg') }}" alt="Green Meadows Phase 1 HOA" class="h-8 w-auto sm:h-10" width="270" height="56">
                </a>

                <nav class="hidden items-center gap-8 text-sm text-zinc-600 md:flex" aria-label="Main navigation">
                    <a href="#about" class="transition-colors hover:text-[#1f6b45] focus-visible:text-[#1f6b45]">About the portal</a>
                    <a href="#homeowners" class="transition-colors hover:text-[#1f6b45] focus-visible:text-[#1f6b45]">For homeowners</a>
                    <a href="#office" class="transition-colors hover:text-[#1f6b45] focus-visible:text-[#1f6b45]">For the HOA</a>
                </nav>

                @auth
                    <a href="{{ route(auth()->user()->homeRoute()) }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-950 transition-colors hover:border-[#1f6b45] hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400">
                        Open portal <span aria-hidden="true">↗</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-950 transition-colors hover:border-[#1f6b45] hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400">
                        Sign in <span aria-hidden="true">↗</span>
                    </a>
                @endauth
            </header>
        </div>

        <main>
            <section class="relative overflow-hidden border-b border-zinc-200" aria-labelledby="hero-title">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-[38rem] bg-[radial-gradient(ellipse_at_70%_20%,rgba(31,107,69,0.09),transparent_58%)]" aria-hidden="true"></div>
                <div class="relative mx-auto grid max-w-7xl gap-14 px-5 py-20 sm:px-8 sm:py-24 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] lg:items-center lg:gap-16 lg:px-10 lg:py-32">
                    <div class="max-w-2xl">
                        <p class="mb-7 flex items-center gap-3 text-xs font-semibold tracking-[0.2em] text-[#1f6b45] uppercase">
                            <span class="h-px w-7 bg-emerald-400"></span>
                            Green Meadows Phase 1 HOA
                        </p>
                        <h1 id="hero-title" class="max-w-[14ch] text-5xl font-semibold leading-[1.06] tracking-[-0.055em] text-zinc-950 sm:text-6xl xl:text-7xl">
                            Property, dues, and requests in one place.
                        </h1>
                        <p class="mt-7 max-w-xl text-base leading-8 text-zinc-600 sm:text-lg">
                            Homeowners can review their accounts and requests. HOA staff can manage records, collections, facilities, and gate checks in a shared workspace.
                        </p>
                        <div class="mt-10 flex flex-wrap items-center gap-5">
                            @auth
                                <a href="{{ route(auth()->user()->homeRoute()) }}" class="inline-flex items-center justify-center gap-3 rounded-lg bg-[#1f6b45] px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-[#288458] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400">
                                    Open your portal <span aria-hidden="true">↗</span>
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-3 rounded-lg bg-[#1f6b45] px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-[#288458] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400">
                                    Sign in to the portal <span aria-hidden="true">↗</span>
                                </a>
                            @endauth
                            <a href="#about" class="inline-flex items-center gap-2 text-sm font-medium text-zinc-700 transition-colors hover:text-[#1f6b45] focus-visible:text-[#1f6b45]">
                                See what it covers <span aria-hidden="true">↓</span>
                            </a>
                        </div>
                        <p class="mt-6 text-xs text-zinc-500">Access is provided by the HOA office.</p>
                    </div>

                    <div class="relative mx-auto w-full max-w-2xl" aria-label="Preview of the portal's property and office sections">
                        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-[0_24px_70px_rgba(29,61,42,0.08)]">
                            <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 sm:px-6">
                                <div class="flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-emerald-400"></span>
                                    <span class="text-xs font-medium text-zinc-700">Green Meadows portal</span>
                                </div>
                                <span class="text-[11px] text-zinc-500">Workspace overview</span>
                            </div>
                            <div class="grid sm:grid-cols-[10.5rem_minmax(0,1fr)]">
                                <div class="hidden border-r border-zinc-200 bg-[#f5f8f4] p-5 sm:block">
                                    <p class="mb-5 text-[10px] font-semibold tracking-[0.18em] text-zinc-500 uppercase">Workspace</p>
                                    <div class="grid gap-2 text-xs">
                                        <span class="rounded-md bg-emerald-50 px-3 py-2.5 font-medium text-[#1f6b45]">Overview</span>
                                        <span class="px-3 py-2.5 text-zinc-600">Properties</span>
                                        <span class="px-3 py-2.5 text-zinc-600">Accounts</span>
                                        <span class="px-3 py-2.5 text-zinc-600">Requests</span>
                                        <span class="px-3 py-2.5 text-zinc-600">Gate check</span>
                                    </div>
                                </div>
                                <div class="p-5 sm:p-7">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-[10px] font-semibold tracking-[0.17em] text-[#1f6b45] uppercase">At a glance</p>
                                            <h2 class="mt-2 text-xl font-semibold tracking-tight text-zinc-950">Inside the portal</h2>
                                        </div>
                                        <span class="hidden rounded-full border border-zinc-200 px-2.5 py-1 text-[10px] text-zinc-600 sm:inline-flex">HOA</span>
                                    </div>
                                    <div class="mt-7 grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-xl border border-zinc-200 bg-[#f8faf7] p-4">
                                            <span class="mb-6 flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-sm font-medium text-[#1f6b45]">01</span>
                                            <p class="text-sm font-semibold text-zinc-950">Property profiles</p>
                                            <p class="mt-1 text-xs leading-5 text-zinc-600">Ownership, household, vehicles</p>
                                        </div>
                                        <div class="rounded-xl border border-zinc-200 bg-[#f8faf7] p-4">
                                            <span class="mb-6 flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-sm font-medium text-[#1f6b45]">02</span>
                                            <p class="text-sm font-semibold text-zinc-950">Dues &amp; receipts</p>
                                            <p class="mt-1 text-xs leading-5 text-zinc-600">Statements, charges, payments</p>
                                        </div>
                                        <div class="rounded-xl border border-zinc-200 bg-[#f8faf7] p-4">
                                            <span class="mb-6 flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-sm font-medium text-[#1f6b45]">03</span>
                                            <p class="text-sm font-semibold text-zinc-950">Community requests</p>
                                            <p class="mt-1 text-xs leading-5 text-zinc-600">Cases, services, facilities</p>
                                        </div>
                                        <div class="rounded-xl border border-zinc-200 bg-[#f8faf7] p-4">
                                            <span class="mb-6 flex size-8 items-center justify-center rounded-lg bg-emerald-50 text-sm font-medium text-[#1f6b45]">04</span>
                                            <p class="text-sm font-semibold text-zinc-950">Gate check</p>
                                            <p class="mt-1 text-xs leading-5 text-zinc-600">Vehicle and pass lookup</p>
                                        </div>
                                    </div>
                                    <div class="mt-5 flex items-center justify-between border-t border-zinc-200 pt-4 text-[11px] text-zinc-500">
                                        <span>Property and office records</span>
                                        <span class="text-[#1f6b45]">Green Meadows Phase 1</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="about" class="scroll-mt-8 border-b border-zinc-200 bg-[#f1f5f0]" aria-labelledby="about-title">
                <div class="mx-auto grid max-w-7xl gap-10 px-5 py-20 sm:px-8 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-20 lg:px-10 lg:py-24">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.2em] text-[#1f6b45] uppercase">Built for the way the HOA works</p>
                        <h2 id="about-title" class="mt-5 max-w-md text-3xl font-semibold leading-tight tracking-[-0.04em] text-zinc-950 sm:text-4xl">Made for homeowners and the HOA team.</h2>
                    </div>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div id="homeowners" class="scroll-mt-8 border-t border-zinc-300 pt-5">
                            <span class="text-xs font-semibold text-[#1f6b45]">01 / Homeowners</span>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-950">Know where things stand.</h3>
                            <p class="mt-3 text-sm leading-7 text-zinc-600">Review your property, statements, receipts, notices, and requests from one account.</p>
                        </div>
                        <div id="office" class="scroll-mt-8 border-t border-zinc-300 pt-5">
                            <span class="text-xs font-semibold text-[#1f6b45]">02 / HOA office</span>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-950">Keep work connected.</h3>
                            <p class="mt-3 text-sm leading-7 text-zinc-600">Manage property records, billing, correspondence, facilities, and follow-up with a shared history.</p>
                        </div>
                        <div class="border-t border-zinc-300 pt-5">
                            <span class="text-xs font-semibold text-[#1f6b45]">03 / Treasurer</span>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-950">See the full account.</h3>
                            <p class="mt-3 text-sm leading-7 text-zinc-600">Track dues, record payments, and review charges and balances by property.</p>
                        </div>
                        <div class="border-t border-zinc-300 pt-5">
                            <span class="text-xs font-semibold text-[#1f6b45]">04 / Gate team</span>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-950">Check with confidence.</h3>
                            <p class="mt-3 text-sm leading-7 text-zinc-600">Look up registered vehicles, stickers, staff passes, and household records at the gate.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto flex max-w-7xl flex-col gap-7 px-5 py-20 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10 lg:py-24" aria-labelledby="access-title">
                <div>
                    <p class="text-xs font-semibold tracking-[0.2em] text-[#1f6b45] uppercase">Ready to get started?</p>
                    <h2 id="access-title" class="mt-4 text-3xl font-semibold tracking-[-0.04em] text-zinc-950 sm:text-4xl">Your community portal is here.</h2>
                    <p class="mt-3 text-sm leading-7 text-zinc-600">Sign in with the account provided by the Green Meadows HOA office.</p>
                </div>
                @auth
                    <a href="{{ route(auth()->user()->homeRoute()) }}" class="inline-flex w-fit shrink-0 items-center gap-3 rounded-lg bg-[#1f6b45] px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-[#288458] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400">Open portal <span aria-hidden="true">↗</span></a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex w-fit shrink-0 items-center gap-3 rounded-lg bg-[#1f6b45] px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-[#288458] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400">Sign in <span aria-hidden="true">↗</span></a>
                @endauth
            </section>
        </main>

        <footer class="border-t border-zinc-200">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-7 text-xs text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
                <span>Green Meadows Phase 1 Homeowners Association</span>
                <span>For homeowners and authorized HOA staff.</span>
            </div>
        </footer>
    </body>
</html>
