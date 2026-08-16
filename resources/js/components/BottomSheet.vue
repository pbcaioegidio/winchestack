<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-[9999] pointer-events-none">
            <!-- Backdrop -->
            <Transition name="bs-backdrop">
                <div
                    v-if="visible"
                    class="absolute inset-0 bg-slate-900/40 dark:bg-black/70 backdrop-blur-sm pointer-events-auto"
                    @click="dismiss"
                ></div>
            </Transition>

            <!-- Sheet -->
            <Transition name="bs-sheet" @after-leave="onAfterLeave">
                <div
                    v-if="visible"
                    class="absolute inset-x-0 bottom-0 mx-auto flex w-full flex-col rounded-t-2xl shadow-2xl pointer-events-auto max-h-[92dvh] border-t bg-white border-slate-200 dark:bg-slate-800 dark:border-white/10"
                    :class="maxWidthClass"
                    :style="sheetStyle"
                    role="dialog"
                    aria-modal="true"
                >
                    <!-- Alca de arrastar -->
                    <div
                        class="shrink-0 pt-3 pb-1.5 flex justify-center cursor-grab touch-none select-none"
                        @pointerdown="onHandleDown"
                    >
                        <div class="h-1.5 w-10 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                    </div>

                    <!-- Cabecalho (opcional) -->
                    <div
                        v-if="title || $slots.header"
                        class="shrink-0 flex items-start justify-between gap-3 px-5 pb-3 border-b border-slate-200 dark:border-white/10"
                    >
                        <slot name="header">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold truncate text-slate-900 dark:text-white">{{ title }}</h2>
                                <p v-if="subtitle" class="text-xs truncate text-slate-500">{{ subtitle }}</p>
                            </div>
                        </slot>
                        <button
                            type="button"
                            @click="dismiss"
                            aria-label="Fechar"
                            class="shrink-0 transition-colors p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:text-white dark:hover:bg-slate-700"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Conteudo -->
                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-5 pt-4 pb-[calc(env(safe-area-inset-bottom)+1.25rem)]">
                        <slot :close="dismiss" />
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    maxWidthClass: { type: String, default: 'sm:max-w-lg' },
})

const emit = defineEmits(['close'])

const visible = ref(false)

const dragging = ref(false)
const snapping = ref(false)
const dragOffset = ref(0)
let startY = 0
const DISMISS_THRESHOLD = 110

const sheetStyle = computed(() => {
    if (dragging.value) {
        return { transform: 'translateY(' + dragOffset.value + 'px)', transition: 'none' }
    }
    if (snapping.value) {
        return { transform: 'translateY(0px)', transition: 'transform .25s ease-out' }
    }
    return {}
})

function onHandleDown(e) {
    if (e.pointerType === 'mouse' && e.button !== 0) return
    startY = e.clientY
    dragging.value = true
    snapping.value = false
    dragOffset.value = 0
    window.addEventListener('pointermove', onPointerMove, { passive: false })
    window.addEventListener('pointerup', onPointerUp)
    window.addEventListener('pointercancel', onPointerUp)
}

function onPointerMove(e) {
    if (!dragging.value) return
    e.preventDefault()
    dragOffset.value = Math.max(0, e.clientY - startY)
}

function onPointerUp() {
    window.removeEventListener('pointermove', onPointerMove)
    window.removeEventListener('pointerup', onPointerUp)
    window.removeEventListener('pointercancel', onPointerUp)
    const shouldClose = dragOffset.value > DISMISS_THRESHOLD
    dragging.value = false
    if (shouldClose) {
        dragOffset.value = 0
        dismiss()
    } else {
        snapping.value = true
        dragOffset.value = 0
        setTimeout(() => { snapping.value = false }, 260)
    }
}

function dismiss() {
    if (!visible.value) return
    visible.value = false
}

function onAfterLeave() {
    emit('close')
}

function onKeydown(e) {
    if (e.key === 'Escape') dismiss()
}

watch(
    () => props.open,
    (val) => {
        if (val) {
            dragOffset.value = 0
            snapping.value = false
            visible.value = true
        } else {
            visible.value = false
        }
    },
)

onMounted(() => {
    document.addEventListener('keydown', onKeydown)
    requestAnimationFrame(() => {
        if (props.open) visible.value = true
    })
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown)
    window.removeEventListener('pointermove', onPointerMove)
    window.removeEventListener('pointerup', onPointerUp)
    window.removeEventListener('pointercancel', onPointerUp)
})
</script>

<style scoped>
.bs-backdrop-enter-active,
.bs-backdrop-leave-active {
    transition: opacity .25s ease;
}
.bs-backdrop-enter-from,
.bs-backdrop-leave-to {
    opacity: 0;
}

.bs-sheet-enter-active {
    transition: transform .32s cubic-bezier(.32, .72, 0, 1);
}
.bs-sheet-leave-active {
    transition: transform .24s ease-in;
}
.bs-sheet-enter-from,
.bs-sheet-leave-to {
    transform: translateY(100%);
}
</style>
