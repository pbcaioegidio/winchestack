<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import {
    Router, LogOut, Eye, Wifi, Cable, Cctv, Smartphone,
    Monitor, Tv, HardDrive, HelpCircle, RefreshCw,
    Sun, Server, Cast, Lightbulb, Printer, Globe,
    Pencil, Ban, ShieldCheck, PanelLeft, PanelTop,
} from '@lucide/vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import EditDeviceModal from '@/components/EditDeviceModal.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'

const props = defineProps({
    viewers: { type: Object, required: true },
    network: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    refresh: { type: Function, default: () => {} },
})

const view = ref('cameras') // 'cameras' | 'network'

// ── Menu lateral e barra de cima recolhíveis (persistidos) ───────
// Cada um abre/fecha por conta própria e continua assim após F5.
const SIDEBAR_KEY = 'winchestack:sidebar'
const sidebarOpen = ref(localStorage.getItem(SIDEBAR_KEY) !== 'closed')
watch(sidebarOpen, (v) => localStorage.setItem(SIDEBAR_KEY, v ? 'open' : 'closed'))

const TOPBAR_KEY = 'winchestack:topbar'
const topbarOpen = ref(localStorage.getItem(TOPBAR_KEY) !== 'closed')
watch(topbarOpen, (v) => localStorage.setItem(TOPBAR_KEY, v ? 'open' : 'closed'))

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

// Rótulo da conexão: "Wi-Fi 5GHz · forte", "Wi-Fi 2.4GHz · bom", "Cabo" ou "—".
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

// Subtítulo: fabricante (dica do que é) + MAC.
function deviceSub(c) {
    const parts = []
    if (c.vendor) parts.push(c.vendor)
    if (c.mac) parts.push(c.mac)
    return parts.join(' · ')
}

// ── Abas por categoria (filtro da tabela) ────────────────────────
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
        { id: 'solar', label: 'Placas solares' },
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

const onlineClients = computed(() => props.network.clients?.filter((c) => c.online) ?? [])
</script>

<template>
    <div class="relative h-dvh flex flex-col overflow-hidden bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <!-- TOP BAR (recolhível) -->
        <header v-show="topbarOpen" class="relative z-40 shrink-0 h-14 flex items-center gap-2 px-3 border-b backdrop-blur border-slate-200 bg-white/80 dark:border-white/10 dark:bg-slate-900/60">
            <button @click="sidebarOpen = !sidebarOpen" title="Mostrar/ocultar menu" class="grid place-items-center h-9 w-9 rounded-lg transition text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/10">
                <PanelLeft class="h-5 w-5" />
            </button>
            <button @click="topbarOpen = false" title="Ocultar barra" class="grid place-items-center h-9 w-9 rounded-lg transition text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/10">
                <PanelTop class="h-5 w-5" />
            </button>
            <span class="grid place-items-center h-8 w-8 rounded-lg bg-indigo-500/15 ring-1 ring-indigo-400/30 ml-1">
                <Router class="h-4.5 w-4.5 text-indigo-500 dark:text-indigo-400" />
            </span>
            <span class="font-semibold tracking-tight">Winchestack</span>

            <div class="ml-3 flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                <span class="flex items-center gap-1.5">
                    <Eye class="h-3.5 w-3.5" /> {{ viewers.total }} assistindo
                </span>
                <span class="flex items-center gap-1.5">
                    <Wifi class="h-3.5 w-3.5" /> {{ network.online }}/{{ network.count }} na rede
                </span>
            </div>

            <div class="flex-1"></div>
            <RefreshCw v-if="loading" class="h-4 w-4 animate-spin text-slate-400" />
            <ThemeToggle />
            <button
                @click="logout"
                class="grid place-items-center h-9 w-9 rounded-lg transition text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/10"
                title="Sair"
            >
                <LogOut class="h-5 w-5" />
            </button>
        </header>

        <!-- Barra recolhida: controles flutuantes (menu lateral + mostrar barra) -->
        <div v-show="!topbarOpen" class="absolute top-2 left-2 z-50 flex items-center gap-0.5 rounded-lg p-0.5 backdrop-blur ring-1 bg-white/80 ring-slate-200 dark:bg-slate-900/70 dark:ring-white/10">
            <button @click="sidebarOpen = !sidebarOpen" title="Mostrar/ocultar menu" class="grid place-items-center h-8 w-8 rounded-md transition text-slate-600 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-white/10">
                <PanelLeft class="h-4.5 w-4.5" />
            </button>
            <button @click="topbarOpen = true" title="Mostrar barra" class="grid place-items-center h-8 w-8 rounded-md transition text-slate-600 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-white/10">
                <PanelTop class="h-4.5 w-4.5" />
            </button>
        </div>

        <div class="flex-1 min-h-0 flex">
            <!-- SIDEBAR -->
            <aside v-if="sidebarOpen" class="w-56 shrink-0 border-r p-3 flex flex-col gap-1 border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900/40">
                <button
                    @click="view = 'cameras'"
                    :class="view === 'cameras' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5'"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
                >
                    <Eye class="h-4.5 w-4.5" /> Câmeras
                    <span class="ml-auto text-xs tabular-nums opacity-70">{{ viewers.total }}</span>
                </button>
                <button
                    @click="view = 'network'"
                    :class="view === 'network' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5'"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
                >
                    <Wifi class="h-4.5 w-4.5" /> Rede
                    <span class="ml-auto text-xs tabular-nums opacity-70">{{ network.online }}</span>
                </button>
            </aside>

            <!-- CONTENT -->
            <main class="flex-1 min-w-0 overflow-y-auto p-6">
                <!-- ── CÂMERAS ── -->
                <section v-if="view === 'cameras'">
                    <div class="mb-5">
                        <h1 class="text-lg font-semibold">Quem está assistindo</h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Espectadores das câmeras agora (descontando o gravador).</p>
                    </div>

                    <div v-if="!viewers.available" class="rounded-xl border border-dashed p-8 text-center text-sm text-slate-500 border-slate-300 dark:border-white/10 dark:text-slate-400">
                        Sem dados de espectadores (relay do watchtower inacessível). Com o relay no ar, isso aparece automaticamente.
                    </div>

                    <div v-else class="grid grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-3">
                        <div
                            v-for="cam in viewers.cameras"
                            :key="cam.id"
                            class="rounded-xl border p-4 border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.03]"
                        >
                            <div class="flex items-center gap-2 mb-3">
                                <span class="h-2 w-2 rounded-full" :class="cam.pushing ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'"></span>
                                <span class="text-sm font-medium truncate">{{ cam.name }}</span>
                            </div>
                            <div class="flex items-end justify-between">
                                <div class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                                    <Eye class="h-4 w-4" />
                                    <span class="text-xs">assistindo</span>
                                </div>
                                <span class="text-2xl font-bold tabular-nums" :class="cam.people > 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 dark:text-slate-600'">{{ cam.people }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de espectadores (quem é cada um) -->
                    <div v-if="viewers.available && viewers.people.length" class="mt-7">
                        <h2 class="text-sm font-semibold mb-3 text-slate-700 dark:text-slate-300">
                            Espectadores ({{ viewers.people.length }})
                        </h2>
                        <div class="rounded-xl border overflow-hidden border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.03]">
                            <table class="w-full text-sm">
                                <thead class="text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500 border-b border-slate-200 dark:border-white/10">
                                    <tr>
                                        <th class="px-4 py-2.5 font-medium">Dispositivo</th>
                                        <th class="px-4 py-2.5 font-medium">IP</th>
                                        <th class="px-4 py-2.5 font-medium">Navegador</th>
                                        <th class="px-4 py-2.5 font-medium">Sistema</th>
                                        <th class="px-4 py-2.5 font-medium">Câmeras</th>
                                        <th class="px-4 py-2.5 font-medium">Desde</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                    <tr v-for="(p, i) in viewers.people" :key="p.ip + p.browser + i">
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-2.5">
                                                <component :is="viewerIcon(p)" class="h-4 w-4 shrink-0 text-slate-400" />
                                                <span class="truncate">{{ viewerName(p) }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 tabular-nums text-slate-600 dark:text-slate-300">{{ p.ip }}</td>
                                        <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ p.browser }}</td>
                                        <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ p.os }}</td>
                                        <td class="px-4 py-2.5 tabular-nums text-slate-500 dark:text-slate-400">{{ p.cameras }}</td>
                                        <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ sinceLabel(p.since) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- ── REDE ── -->
                <section v-else>
                    <div class="mb-5">
                        <h1 class="text-lg font-semibold">Dispositivos na rede</h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Quem está conectado e ativo na sua rede local (via roteador).</p>
                    </div>

                    <div v-if="!network.available" class="rounded-xl border border-dashed p-8 text-center text-sm text-slate-500 border-slate-300 dark:border-white/10 dark:text-slate-400">
                        Roteador inacessível (SSH). Confira OPENWRT_HOST/chave no .env.
                    </div>

                    <template v-else>
                        <div class="rounded-xl border overflow-hidden border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.03]">
                        <div class="flex flex-wrap gap-1.5 p-3 border-b border-slate-200 dark:border-white/10">
                            <button
                                v-for="t in netTabs"
                                :key="t.id"
                                @click="netFilter = t.id"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium transition"
                                :class="netFilter === t.id ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/[0.04] dark:text-slate-300 dark:hover:bg-white/[0.08]'"
                            >
                                {{ t.label }}
                                <span class="ml-1 opacity-70 tabular-nums">{{ t.id === 'todos' ? network.count : (netGroupCounts[t.id] || 0) }}</span>
                            </button>
                        </div>
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500 border-b border-slate-200 dark:border-white/10">
                                <tr>
                                    <th class="px-4 py-2.5 font-medium">Dispositivo</th>
                                    <th class="px-4 py-2.5 font-medium">IP</th>
                                    <th class="px-4 py-2.5 font-medium">Tipo</th>
                                    <th class="px-4 py-2.5 font-medium">Conexão</th>
                                    <th v-if="network.traffic_available" class="px-4 py-2.5 font-medium">Tráfego</th>
                                    <th class="px-4 py-2.5 font-medium">Status</th>
                                    <th class="px-4 py-2.5 font-medium ">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                <tr v-for="c in filteredClients" :key="c.mac" :class="[c.online ? '' : 'opacity-50', c.blocked ? 'bg-rose-50/60 dark:bg-rose-500/5' : '']">
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2.5">
                                            <component :is="kindIcon(c.kind)" class="h-4 w-4 shrink-0 text-slate-400" />
                                            <span class="truncate">{{ c.name || 'Desconhecido' }}</span>
                                            <span v-if="c.novo" class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">novo</span>
                                        </div>
                                        <div class="text-[11px] text-slate-400 dark:text-slate-600 ml-6.5">{{ deviceSub(c) }}</div>
                                    </td>
                                    <td class="px-4 py-2.5 tabular-nums text-slate-600 dark:text-slate-300">{{ c.ip || '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ kindLabel(c.kind) }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                                            <component :is="connIcon(c)" class="h-3.5 w-3.5" />
                                            <span class="text-xs">{{ connLabel(c) }}</span>
                                        </span>
                                    </td>
                                    <td v-if="network.traffic_available" class="px-4 py-2.5 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                        <span class="text-sky-600 dark:text-sky-400">↓ {{ formatBytes(c.rx) }}</span>
                                        <span class="mx-1 text-slate-300 dark:text-slate-600">·</span>
                                        <span class="text-violet-600 dark:text-violet-400">↑ {{ formatBytes(c.tx) }}</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span v-if="c.blocked" class="inline-flex items-center gap-1.5 text-rose-600 dark:text-rose-400 text-xs font-medium">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Bloqueado
                                        </span>
                                        <template v-else>
                                            <span v-if="c.online && !c.idle" class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 text-xs font-medium">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Online
                                            </span>
                                            <span v-else-if="c.idle" title="Visto há pouco na rede, mas sem conexão ativa confirmada agora" class="inline-flex items-center gap-1.5 text-amber-600 dark:text-amber-400 text-xs">
                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Ocioso
                                            </span>
                                            <span v-else class="inline-flex items-center gap-1.5 text-slate-400 text-xs">
                                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Offline
                                            </span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-1">
                                            <button @click="openEdit(c)" title="Editar nome/tipo" class="grid place-items-center h-8 w-8 rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-white/10 dark:hover:text-indigo-400">
                                                <Pencil class="h-4 w-4" />
                                            </button>
                                            <button v-if="!c.blocked" @click="blockDevice(c)" :disabled="busy === c.mac" title="Bloquear internet" class="grid place-items-center h-8 w-8 rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400 disabled:opacity-50">
                                                <Ban class="h-4 w-4" />
                                            </button>
                                            <button v-else @click="unblockDevice(c)" :disabled="busy === c.mac" title="Liberar internet" class="grid place-items-center h-8 w-8 rounded-lg text-emerald-600 transition hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10 disabled:opacity-50">
                                                <ShieldCheck class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </template>
                </section>
            </main>
        </div>

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
    </div>
</template>
