{{-- Botón principal de las pantallas de acceso: marino, con flecha que avanza al pasar el cursor. --}}
<button type="submit"
    class="group relative flex h-12 w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-udg-800 px-5 text-[15px] font-semibold text-white shadow-theme-sm transition duration-200 ease-out hover:-translate-y-px hover:bg-udg-700 hover:shadow-theme-md focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-real-500/30 active:translate-y-0 active:scale-[0.99] disabled:cursor-wait disabled:opacity-70 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
    <span>{{ $slot }}</span>
    <svg class="size-4 transition-transform duration-200 group-hover:translate-x-1 motion-reduce:transition-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
    </svg>
</button>
