<script setup>
/** Public asset: `public/Banner/profile_banner.jpg` */
const BANNER_SRC = '/Banner/profile_banner.jpg';
</script>

<template>
  <!--
    Responsive strip: aspect-ratio tracks width; min/max height + svh cap short viewports (mobile landscape).
    Parent profile card supplies horizontal padding; this stays full width inside that column.
  -->
  <div
    class="relative isolate w-full overflow-hidden bg-slate-900
      aspect-[20/9] min-h-[6.75rem] max-h-[min(12rem,42svh)]
      sm:aspect-[21/9] sm:min-h-[7.25rem] sm:max-h-[min(14rem,36svh)]
      md:min-h-[8rem] md:max-h-[15rem]
      lg:max-h-[17rem] xl:max-h-[19rem] 2xl:max-h-[21rem]"
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
