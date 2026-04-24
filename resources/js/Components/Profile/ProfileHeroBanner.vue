<script setup>
/** Public asset: `public/Banner/p_banner.png` */
const BANNER_SRC = '/Banner/p_banner.png';
</script>

<template>
  <!-- Compact strip: wider aspect ratio + lower caps than before -->
  <div
    class="relative isolate w-full overflow-hidden bg-slate-900
      aspect-[3/1] min-h-[4.75rem] max-h-[min(8.5rem,32svh)]
      sm:min-h-[5.25rem] sm:max-h-[min(9.5rem,28svh)]
      md:aspect-[28/9] md:min-h-[5.5rem] md:max-h-[10rem]
      lg:max-h-[11rem] xl:max-h-[12rem]"
  >
    <img
      :src="BANNER_SRC"
      alt=""
      width="1600"
      height="400"
      class="absolute inset-0 h-full w-full object-cover object-center sm:object-[center_45%] md:object-center"
      decoding="async"
      fetchpriority="high"
      sizes="(min-width: 1280px) 80rem, 100vw"
    />
    <div
      class="pointer-events-none absolute inset-0 bg-gradient-to-b from-slate-950/15 via-transparent to-slate-950/25"
      aria-hidden="true"
    />
  </div>
</template>
