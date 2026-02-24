<template>
  <div class="sidebar-overlay" id="sidebar-overlay" @click="closeMobile"></div>
</template>
<script setup>
function closeMobile() {
  const sidebar = document.getElementById('sidebar')
  const overlay = document.getElementById('sidebar-overlay')
  if (sidebar) sidebar.classList.remove('mobile-open')
  if (overlay) overlay.classList.remove('visible')
}
</script>
