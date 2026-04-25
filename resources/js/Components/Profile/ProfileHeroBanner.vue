<script setup>
const BANNER_SRC = '/Banner/p_banner_1.png';
</script>

<template>
  <div class="w-full bg-slate-900">
    
    <!-- Responsive Image Container -->
    <img
      :src="BANNER_SRC"
      alt="Profile Verified Banner"
      class="w-full h-auto object-contain"
      width="1600"
      height="400"
      loading="eager"
      decoding="async"
      fetchpriority="high"
    />

  </div>
</template>