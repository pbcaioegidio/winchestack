<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import {
    Router, LogOut, Eye, Wifi, Cable, Cctv, Smartphone,
    Monitor, Tv, HardDrive, HelpCircle,
    Sun, Server, Cast, Lightbulb, Printer, Globe,
    Pencil, Ban, ShieldCheck, ChevronDown,
} from '@lucide/vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import EditDeviceModal from '@/components/EditDeviceModal.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import BottomSheet from '@/components/BottomSheet.vue'

const props = defineProps({
    viewers: { type: Object, required: true },
    network: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    refresh: { type: Function, default: () => {} },
})

const tab = ref('cameras') // 'cameras' | 'network'

function logout() {
    router.post('/logout')
}

const kindIcon = (k) => ({
    solar: Sun, camera: Cctv, celular: Smartphone, computador: Monitor, servidor: Server,
    tv: Tv, tvbox: Cast, smarthome: Lightbulb, printer: Printer, router: Router, dispositivo: HardDrive,
}[k] || HelpCircle)

const kindLabel = (k) => ({
    solar: 'Placa solar', camera: 'Câmera', celular: 'Celular', computador: 'Computador', servidor: 'Servidor',
    tv: 'TV', tvbox: 'TV box', smarthome: 'Casa inteligente', printer: 'Impressora', router: 'Roteador', dispositivo: 'Dispositivo',
}[k] || 'Desconhecido')

function signalLabel(dbm) {
    if (dbm === null || dbm === undefined) return ''
    if (dbm >= -55) return 'forte'
    if (dbm >= -67) return 'bom'
    if (dbm >= -75) return 'fraco'
    return 'ruim'
}

function connLabel(c) {
    if (c.connection === 'wifi') {
        const b = c.band ? ` ${c.band}GHz` : ''
        const s = c.signal != null ? ` · ${signalLabel(c.signal)}` : ''
        return `Wi-Fi${b}${s}`
    }
    if (c.connection === 'cabo') return 'Cabo'
    return '—'
}

function connIcon(c) {
    if (c.connection === 'wifi') return Wifi
    if (c.connection === 'cabo') return Cable
    return HelpCircle
}

// ── Filtro por categoria ─────────────────────────────────────────
const netFilter = ref('todos')

function groupOf(kind) {
    if (kind === 'camera') return 'cameras'
    if (kind === 'computador' || kind === 'servidor') return 'computadores'
    if (kind === 'celular') return 'celulares'
    if (kind === 'tv' || kind === 'tvbox') return 'tv'
    if (kind === 'solar') return 'solar'
    if (kind === 'smarthome') return 'smarthome'
    if (kind === 'printer' || kind === 'router') return 'outros'
    return 'desconhecidos'
}

const netGroupCounts = computed(() => {
    const counts = {}
    for (const c of props.network.clients ?? []) {
        const g = groupOf(c.kind)
        counts[g] = (counts[g] || 0) + 1
    }
    return counts
})

const netTabs = computed(() => {
    const defs = [
        { id: 'cameras', label: 'Câmeras' },
        { id: 'computadores', label: 'Computadores' },
        { id: 'celulares', label: 'Celulares' },
        { id: 'tv', label: 'TV' },
        { id: 'solar', label: 'Solar' },
        { id: 'smarthome', label: 'Casa inteligente' },
        { id: 'outros', label: 'Outros' },
        { id: 'desconhecidos', label: 'Desconhecidos' },
    ]
    const present = defs.filter((d) => (netGroupCounts.value[d.id] || 0) > 0)
    return [{ id: 'todos', label: 'Todos' }, ...present]
})

const filteredClients = computed(() => {
    const list = props.network.clients ?? []
    if (netFilter.value === 'todos') return list
    return list.filter((c) => groupOf(c.kind) === netFilter.value)
})

// Filtro de categoria no mobile: botão que abre um bottom sheet.
const filterSheet = ref(false)
const currentFilterLabel = computed(() => {
    const t = netTabs.value.find((x) => x.id === netFilter.value) || netTabs.value[0]
    const count = t.id === 'todos' ? props.network.count : (netGroupCounts.value[t.id] || 0)
    return `${t.label} (${count})`
})

// ── Editar (modal) + bloquear por dispositivo ────────────────────
const editing = ref(null)
const busy = ref(null)

function openEdit(c) {
    editing.value = c
}
function onSaved() {
    editing.value = null
    props.refresh()
}
const confirming = ref(null)

function blockDevice(c) {
    confirming.value = c
}
async function performBlock() {
    const c = confirming.value
    confirming.value = null
    if (!c) return
    busy.value = c.mac
    try {
        await window.axios.post(`/api/devices/${c.mac}/block`)
        await props.refresh()
    } catch (e) {
        window.alert('Não consegui bloquear (roteador inacessível?).')
    } finally {
        busy.value = null
    }
}
async function unblockDevice(c) {
    busy.value = c.mac
    try {
        await window.axios.post(`/api/devices/${c.mac}/unblock`)
        await props.refresh()
    } catch (e) {
        window.alert('Não consegui liberar.')
    } finally {
        busy.value = null
    }
}

// Cruza o IP do espectador com os dispositivos da rede para mostrar o nome.
function deviceNameByIp(ip) {
    const c = props.network.clients?.find((x) => x.ip === ip)
    return c?.name || c?.vendor || null
}

// IP de LAN (rede local)? Senão é acesso pela internet (NAT): o relay
// só enxerga o IP público, então não dá pra dizer qual aparelho é.
function isLanIp(ip) {
    if (!ip) return false
    if (ip === '127.0.0.1' || ip.startsWith('::1') || ip.startsWith('::ffff:127.')) return true
    return /^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(ip)
}

// Nome do espectador: aparelho da LAN (se o IP casar), senão "Internet (fora
// da rede)" deixando claro que é acesso remoto, não um aparelho local.
function viewerName(p) {
    const local = deviceNameByIp(p.ip)
    if (local) return local
    if (p.ip && !isLanIp(p.ip)) return 'Internet (fora da rede)'
    return kindLabel(p.kind)
}

function viewerIcon(p) {
    if (!deviceNameByIp(p.ip) && p.ip && !isLanIp(p.ip)) return Globe
    return kindIcon(p.kind)
}

function sinceLabel(ms) {
    if (!ms) return ''
    const s = Math.max(0, Math.floor((Date.now() - ms) / 1000))
    if (s < 60) return 'agora'
    const m = Math.floor(s / 60)
    if (m < 60) return `há ${m} min`
    const h = Math.floor(m / 60)
    return `há ${h}h`
}

function formatBytes(n) {
    if (n === null || n === undefined) return '—'
    if (n < 1024) return `${n} B`
    const u = ['KB', 'MB', 'GB', 'TB']
    let i = -1
    let v = n
    do { v /= 1024; i++ } while (v >= 1024 && i < u.length - 1)
    return `${v.toFixed(v < 10 ? 1 : 0)} ${u[i]}`
}
</script>

<template>
    <div class="h-dvh flex flex-col overflow-hidden bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <!-- TOP BAR -->
        <header class="shrink-0 h-14 flex items-center gap-2.5 px-4 border-b backdrop-blur pt-[env(safe-area-inset-top)] border-slate-200 bg-white/90 dark:border-white/10 dark:bg-slate-900/70">
            <span class="grid place-items-center h-8 w-8 rounded-lg bg-indigo-500/15 ring-1 ring-indigo-400/30">
                <Router class="h-4.5 w-4.5 text-indigo-500 dark:text-indigo-400" />
            </span>
            <span class="font-semibold tracking-tight text-sm">Winchestack</span>
            <div class="flex-1"></div>
            <ThemeToggle />
            <button
                @click="logout"
                class="grid place-items-center h-10 w-10 rounded-xl transition text-slate-500 active:bg-slate-100 dark:text-slate-300 dark:active:bg-white/10"
                aria-label="Sair"
            >
                <LogOut class="h-5 w-5" />
            </button>
        </header>

        <main class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
            <!-- ── CÂMERAS ── -->
            <div v-if="tab === 'cameras'" class="p-3 space-y-3 pb-4">
                <div class="rounded-2xl border p-4 flex items-center justify-between border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.03]">
                    <div>
                        <p class="text-sm font-medium">Assistindo agora</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">telas nas câmeras</p>
                    </div>
                    <span class="text-3xl font-bold tabular-nums text-indigo-600 dark:text-indigo-400">{{ viewers.total }}</span>
                </div>

                <div v-if="!viewers.available" class="rounded-xl border border-dashed p-6 text-center text-sm text-slate-500 border-slate-300 dark:border-white/10 dark:text-slate-400">
                    Sem dados de espectadores (relay inacessível). Aparece em produção.
                </div>

                <div
                    v-for="cam in viewers.cameras"
                    :key="cam.id"
                    class="rounded-xl border p-3.5 flex items-center justify-between border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.03]"
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="h-2 w-2 rounded-full shrink-0" :class="cam.pushing ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'"></span>
                        <span class="text-sm truncate">{{ cam.name }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <Eye class="h-4 w-4 text-slate-400" />
                        <span class="text-lg font-bold tabular-nums" :class="cam.people > 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-600'">{{ cam.people }}</span>
                    </div>
                </div>

                <!-- Espectadores (quem é cada um) -->
                <template v-if="viewers.available && viewers.people.length">
                    <p class="px-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                        Espectadores ({{ viewers.people.length }})
                    </p>
                    <div
                        v-for="(p, i) in viewers.people"
                        :key="p.ip + p.browser + i"
                        class="rounded-xl border p-3 flex items-center gap-3 border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.03]"
                    >
                        <span class="grid place-items-center h-9 w-9 rounded-lg shrink-0 bg-slate-100 dark:bg-white/[0.04]">
                            <component :is="viewerIcon(p)" class="h-4.5 w-4.5 text-slate-400" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium truncate">{{ viewerName(p) }}</p>
                            <p class="text-[11px] truncate text-slate-500 dark:text-slate-400">{{ p.ip }} · {{ p.browser }} · {{ p.os }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs font-medium text-indigo-600 dark:text-indigo-400">{{ p.cameras }} câm</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">{{ sinceLabel(p.since) }}</p>
                        </div>
                    </div>
                </template>
            </div>

            <!-- ── REDE ── -->
            <div v-else class="p-3 space-y-3 pb-4">
                <div class="rounded-2xl border p-4 flex items-center justify-between border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.03]">
                    <div>
                        <p class="text-sm font-medium">Na rede</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">online agora</p>
                    </div>
                    <span class="text-3xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ network.online }}<span class="text-base text-slate-400">/{{ network.count }}</span></span>
                </div>

                <div v-if="network.available">
                    <button
                        @click="filterSheet = true"
                        class="w-full flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm border transition bg-white border-slate-200 text-slate-700 active:bg-slate-50 dark:bg-white/[0.03] dark:border-white/10 dark:text-slate-200"
                    >
                        <span class="font-medium">{{ currentFilterLabel }}</span>
                        <ChevronDown class="h-4 w-4 text-slate-400" />
                    </button>
                </div>

                <div v-if="!network.available" class="rounded-xl border border-dashed p-6 text-center text-sm text-slate-500 border-slate-300 dark:border-white/10 dark:text-slate-400">
                    Roteador inacessível (SSH). Confira o .env.
                </div>

                <div
                    v-for="c in filteredClients"
                    :key="c.mac"
                    class="rounded-xl border p-3 border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.03]"
                    :class="[c.online ? '' : 'opacity-60', c.blocked ? 'ring-1 ring-rose-300 dark:ring-rose-500/30' : '']"
                >
                    <div class="flex items-center gap-3">
                        <span class="grid place-items-center h-9 w-9 rounded-lg shrink-0 bg-slate-100 dark:bg-white/[0.04]">
                            <component :is="kindIcon(c.kind)" class="h-4.5 w-4.5 text-slate-400" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium truncate flex items-center gap-1.5">
                                <span class="truncate">{{ c.name || 'Desconhecido' }}</span>
                                <span v-if="c.novo" class="shrink-0 rounded px-1 py-0.5 text-[9px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">novo</span>
                            </p>
                            <p v-if="!c.name && c.vendor" class="text-[10px] truncate text-slate-400 dark:text-slate-500">{{ c.vendor }}</p>
                            <p class="text-[11px] truncate flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                                <component :is="connIcon(c)" class="h-3 w-3 shrink-0" />
                                <span>{{ connLabel(c) }}</span>
                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                <span class="tabular-nums">{{ c.ip || '—' }}</span>
                            </p>
                            <p v-if="network.traffic_available" class="text-[11px] truncate text-slate-400 dark:text-slate-500">
                                ↓ {{ formatBytes(c.rx) }} · ↑ {{ formatBytes(c.tx) }}
                            </p>
                        </div>
                        <span v-if="c.blocked" class="h-2 w-2 rounded-full bg-rose-500 shrink-0"></span>
                        <span v-else-if="c.online && !c.idle" class="h-2 w-2 rounded-full bg-emerald-500 shrink-0"></span>
                        <span v-else-if="c.idle" class="h-2 w-2 rounded-full bg-amber-500 shrink-0"></span>
                        <span v-else class="h-2 w-2 rounded-full bg-slate-400 shrink-0"></span>
                    </div>
                    <div class="flex items-center gap-2 mt-2.5 pt-2.5 border-t border-slate-100 dark:border-white/5">
                        <span v-if="c.blocked" class="inline-flex items-center gap-1 text-[11px] font-medium text-rose-600 dark:text-rose-400">
                            <Ban class="h-3.5 w-3.5" /> Bloqueado
                        </span>
                        <div class="ml-auto flex items-center gap-2">
                            <button @click="openEdit(c)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-600 bg-slate-100 active:bg-slate-200 dark:text-slate-300 dark:bg-white/[0.05] dark:active:bg-white/10">
                                <Pencil class="h-3.5 w-3.5" /> Editar
                            </button>
                            <button v-if="!c.blocked" @click="blockDevice(c)" :disabled="busy === c.mac" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-rose-600 bg-rose-50 active:bg-rose-100 dark:text-rose-400 dark:bg-rose-500/10 disabled:opacity-50">
                                <Ban class="h-3.5 w-3.5" /> Bloquear
                            </button>
                            <button v-else @click="unblockDevice(c)" :disabled="busy === c.mac" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-emerald-600 bg-emerald-50 active:bg-emerald-100 dark:text-emerald-400 dark:bg-emerald-500/10 disabled:opacity-50">
                                <ShieldCheck class="h-3.5 w-3.5" /> Liberar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- BOTTOM NAV -->
        <nav class="shrink-0 grid grid-cols-2 border-t backdrop-blur pb-[env(safe-area-inset-bottom)] border-slate-200 bg-white/90 dark:border-white/10 dark:bg-slate-900/80">
            <button
                @click="tab = 'cameras'"
                class="flex flex-col items-center justify-center gap-0.5 py-2.5 transition"
                :class="tab === 'cameras' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 dark:text-slate-400'"
            >
                <Eye class="h-5 w-5" />
                <span class="text-[10px] font-medium">Câmeras</span>
            </button>
            <button
                @click="tab = 'network'"
                class="flex flex-col items-center justify-center gap-0.5 py-2.5 transition"
                :class="tab === 'network' ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 dark:text-slate-400'"
            >
                <Wifi class="h-5 w-5" />
                <span class="text-[10px] font-medium">Rede</span>
            </button>
        </nav>

        <EditDeviceModal v-if="editing" :device="editing" @close="editing = null" @saved="onSaved" />
        <ConfirmModal
            v-if="confirming"
            title="Bloquear internet"
            :message="`Cortar a internet de “${confirming.name || confirming.vendor || confirming.mac}”? Você pode liberar depois.`"
            confirm-label="Bloquear"
            cancel-label="Cancelar"
            :danger="true"
            @confirm="performBlock"
            @cancel="confirming = null"
        />
        <BottomSheet :open="filterSheet" title="Filtrar por tipo" @close="filterSheet = false">
            <template #default="{ close }">
                <div class="space-y-1 pb-2">
                    <button
                        v-for="t in netTabs"
                        :key="t.id"
                        @click="netFilter = t.id; close()"
                        class="w-full flex items-center justify-between rounded-xl px-3 py-3 text-sm transition"
                        :class="netFilter === t.id ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300 font-medium' : 'text-slate-700 active:bg-slate-100 dark:text-slate-300 dark:active:bg-white/5'"
                    >
                        <span>{{ t.label }}</span>
                        <span class="tabular-nums text-xs opacity-70">{{ t.id === 'todos' ? network.count : (netGroupCounts[t.id] || 0) }}</span>
                    </button>
                </div>
            </template>
        </BottomSheet>
    </div>
</template>
