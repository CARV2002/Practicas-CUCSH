<div
  x-show="loaded"
  x-init="setTimeout(() => loaded = false, 200)"
  x-transition:leave="transition-opacity duration-200 ease-in motion-reduce:transition-none"
  x-transition:leave-end="opacity-0"
  class="fixed left-0 top-0 z-999999 flex h-screen w-screen items-center justify-center bg-white"
>
  <div
    class="h-16 w-16 animate-spin rounded-full border-4 border-solid border-udg-800 border-t-transparent"
  ></div>
</div>
