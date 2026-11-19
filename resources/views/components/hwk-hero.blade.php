@props([
    'eyebrow' => 'Assets',
    'title',
    'description' => null,
])

<section {{ $attributes->class('hwk-hero relative overflow-hidden rounded-2xl text-white shadow-xl shadow-[#073070]/25 ring-1 ring-white/10') }}>
    <div aria-hidden="true" class="pointer-events-none absolute -top-28 -right-20 size-96 rounded-full bg-[#a0c7f2]/40 blur-3xl"></div>
    <div aria-hidden="true" class="pointer-events-none absolute -bottom-36 -left-20 size-80 rounded-full bg-[#d0e3f9]/30 blur-3xl"></div>
    <div
        aria-hidden="true"
        class="pointer-events-none absolute inset-0 opacity-10 [background-image:linear-gradient(to_right,white_1px,transparent_1px),linear-gradient(to_bottom,white_1px,transparent_1px)] [background-size:2rem_2rem] [mask-image:radial-gradient(ellipse_at_top_right,black,transparent_70%)]"
    ></div>

    <div class="relative flex flex-wrap items-end justify-between gap-4 p-5 sm:px-7 sm:py-6">
        <div class="min-w-0 max-w-3xl">
            <p class="text-xs font-semibold tracking-[0.2em] text-[#d0e3f9] uppercase">{{ $eyebrow }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $title }}</h1>
            @if (filled($description))
                <p class="mt-1 max-w-2xl text-sm text-[#d0e3f9]/90">{{ $description }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</section>
