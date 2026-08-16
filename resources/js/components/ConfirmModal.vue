<template>
    <BottomSheet :open="true" max-width-class="sm:max-w-md" @close="onClose">
        <template #default="{ close }">
            <div class="flex items-start gap-4">
                <div
                    class="flex-none w-11 h-11 rounded-full flex items-center justify-center"
                    :class="iconWrapClass"
                >
                    <svg v-if="danger" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                    <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ title }}</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ message }}</p>
                </div>
            </div>

            <div class="mt-6 flex gap-2">
                <button
                    v-if="!alert"
                    type="button"
                    @click="onCancel(close)"
                    class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors text-slate-700 bg-slate-100 hover:bg-slate-200 dark:text-slate-200 dark:bg-gray-700 dark:hover:bg-gray-600"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    ref="confirmBtn"
                    type="button"
                    @click="onConfirm(close)"
                    class="flex-1 px-4 py-2.5 rounded-lg text-sm font-semibold text-white transition-colors"
                    :class="confirmBtnClass"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </template>
    </BottomSheet>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import BottomSheet from '@/components/BottomSheet.vue'

const props = defineProps({
    title: { type: String, default: 'Confirmar ação' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirmar' },
    cancelLabel: { type: String, default: 'Cancelar' },
    danger: { type: Boolean, default: false },
    alert: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'cancel'])

const confirmBtn = ref(null)
const result = ref(null)

const iconWrapClass = computed(() =>
    props.danger
        ? 'bg-rose-100 text-rose-600 dark:bg-red-500/15 dark:text-red-400'
        : 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',
)
const confirmBtnClass = computed(() =>
    props.danger
        ? 'bg-rose-600 hover:bg-rose-700'
        : 'bg-indigo-600 hover:bg-indigo-700',
)

function onConfirm(close) {
    result.value = 'confirm'
    close()
}

function onCancel(close) {
    result.value = 'cancel'
    close()
}

function onClose() {
    emit(result.value === 'confirm' ? 'confirm' : 'cancel')
}

onMounted(() => {
    setTimeout(() => confirmBtn.value?.focus(), 80)
})
</script>
