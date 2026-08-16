<template>
    <BottomSheet :open="true" max-width-class="sm:max-w-lg" @close="emit('close')">
        <template #header>
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-indigo-500/15 flex items-center justify-center shrink-0">
                    <Pencil class="w-4.5 h-4.5 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Editar dispositivo</h2>
                    <p class="text-xs text-slate-500 truncate tabular-nums">{{ device.mac }}</p>
                </div>
            </div>
        </template>

        <template #default="{ close }">
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Nome</label>
                    <input v-model="name" type="text" maxlength="60" placeholder="Ex.: TV da sala" :class="inputClass" />
                    <p class="text-[11px] text-slate-400 dark:text-slate-600 mt-1">Deixe vazio para usar o nome que o aparelho informa.</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">Tipo</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="k in KINDS"
                            :key="k.value"
                            type="button"
                            @click="kind = k.value"
                            :class="kind === k.value ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-slate-100 border-slate-200 text-slate-600 hover:border-slate-300 dark:bg-slate-700 dark:border-white/10 dark:text-slate-300 dark:hover:border-white/20'"
                            class="flex flex-col items-center gap-1 border rounded-lg py-2 transition-colors"
                        >
                            <component :is="k.icon" class="h-4 w-4" />
                            <span class="text-[11px] font-medium">{{ k.label }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="error" class="rounded-lg p-3 text-sm border bg-rose-50 border-rose-200 text-rose-600 dark:bg-red-900/30 dark:border-red-700 dark:text-red-400">
                    {{ error }}
                </div>

                <div class="flex gap-3 pt-1">
                    <button type="button" @click="close" class="flex-1 py-2.5 rounded-lg text-sm font-medium transition-colors text-slate-700 bg-slate-100 hover:bg-slate-200 dark:text-slate-300 dark:bg-gray-700 dark:hover:bg-gray-600">
                        Cancelar
                    </button>
                    <button type="submit" :disabled="saving" class="flex-1 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white py-2.5 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-2">
                        <LoaderCircle v-if="saving" class="w-4 h-4 animate-spin" />
                        {{ saving ? 'Salvando...' : 'Salvar' }}
                    </button>
                </div>
            </form>
        </template>
    </BottomSheet>
</template>

<script setup>
import { ref } from 'vue'
import {
    Pencil, LoaderCircle, Cctv, Smartphone, Monitor, Server, Tv, Cast,
    Sun, Lightbulb, Printer, Router, HardDrive, HelpCircle,
} from '@lucide/vue'
import BottomSheet from '@/components/BottomSheet.vue'

const props = defineProps({
    device: { type: Object, required: true },
})
const emit = defineEmits(['close', 'saved'])

const KINDS = [
    { value: 'camera', label: 'Câmera', icon: Cctv },
    { value: 'celular', label: 'Celular', icon: Smartphone },
    { value: 'computador', label: 'Computador', icon: Monitor },
    { value: 'servidor', label: 'Servidor', icon: Server },
    { value: 'tv', label: 'TV', icon: Tv },
    { value: 'tvbox', label: 'TV box', icon: Cast },
    { value: 'solar', label: 'Placa solar', icon: Sun },
    { value: 'smarthome', label: 'Casa intelig.', icon: Lightbulb },
    { value: 'printer', label: 'Impressora', icon: Printer },
    { value: 'router', label: 'Roteador', icon: Router },
    { value: 'dispositivo', label: 'Dispositivo', icon: HardDrive },
    { value: 'desconhecido', label: 'Desconhecido', icon: HelpCircle },
]

const inputClass = 'w-full rounded-lg px-3.5 py-2 text-sm border transition focus:outline-none focus:ring-2 focus:ring-indigo-500/40 bg-white border-slate-300 text-slate-900 placeholder-slate-400 dark:bg-slate-700/60 dark:border-white/10 dark:text-white dark:placeholder-slate-500'

const name = ref(props.device.name || '')
const kind = ref(props.device.kind || 'desconhecido')
const saving = ref(false)
const error = ref('')

async function submit() {
    error.value = ''
    saving.value = true
    try {
        await window.axios.post(`/api/devices/${props.device.mac}`, {
            name: name.value || null,
            kind: kind.value,
        })
        emit('saved')
    } catch (e) {
        error.value = 'Não consegui salvar. Tente de novo.'
    } finally {
        saving.value = false
    }
}
</script>
