<script setup>
import { Head } from '@inertiajs/vue3'
import { useDevice } from '@/composables/useDevice'
import { useMonitor } from '@/composables/useMonitor'
import DesktopPanel from '@/components/desktop/DesktopPanel.vue'
import MobilePanel from '@/components/mobile/MobilePanel.vue'

const { isMobile } = useDevice()
const { viewers, network, loading, refresh } = useMonitor()
</script>

<template>
    <Head title="" />
    <MobilePanel v-if="isMobile" :viewers="viewers" :network="network" :loading="loading" :refresh="refresh" />
    <DesktopPanel v-else :viewers="viewers" :network="network" :loading="loading" :refresh="refresh" />
</template>
