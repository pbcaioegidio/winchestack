<script setup>
import { computed } from 'vue'
import { Sun, Moon, Monitor } from '@lucide/vue'
import { useTheme } from '@/composables/useTheme'

const { theme, cycle } = useTheme()

const icon = computed(() =>
    theme.value === 'light' ? Sun : theme.value === 'dark' ? Moon : Monitor,
)
const label = computed(() =>
    theme.value === 'light' ? 'Tema: claro (clique p/ escuro)'
        : theme.value === 'dark' ? 'Tema: escuro (clique p/ sistema)'
        : 'Tema: sistema (clique p/ claro)',
)
</script>

<template>
    <button
        type="button"
        @click="cycle"
        :title="label"
        :aria-label="label"
        class="grid place-items-center h-9 w-9 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white transition"
    >
        <component :is="icon" class="h-5 w-5" />
    </button>
</template>
