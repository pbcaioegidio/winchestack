import { ref, onMounted, onBeforeUnmount } from 'vue'

/**
 * Busca e mantém atualizados os dados do painel:
 *  - /api/viewers → quem está assistindo as câmeras (via relay do watchtower)
 *  - /api/network → quem está na rede local (via roteador OpenWrt)
 *
 * Faz polling a cada 5s e pausa quando a aba fica oculta (economiza recursos).
 * É usado UMA vez (na página Panel) e os dados descem por props.
 */
export function useMonitor() {
    const viewers = ref({ available: false, total: 0, cameras: [], people: [] })
    const network = ref({ available: false, count: 0, online: 0, clients: [] })
    const loading = ref(true)

    let timer = null

    async function fetchViewers() {
        try {
            const { data } = await window.axios.get('/api/viewers')
            viewers.value = data
        } catch (e) {
            viewers.value = { available: false, total: 0, cameras: [], people: [] }
        }
    }

    async function fetchNetwork() {
        try {
            const { data } = await window.axios.get('/api/network')
            network.value = data
        } catch (e) {
            network.value = { available: false, count: 0, online: 0, clients: [] }
        }
    }

    async function refresh() {
        await Promise.all([fetchViewers(), fetchNetwork()])
        loading.value = false
    }

    function schedule() {
        stop()
        timer = setInterval(() => {
            if (document.visibilityState === 'visible') refresh()
        }, 5000)
    }

    function stop() {
        if (timer) {
            clearInterval(timer)
            timer = null
        }
    }

    function onVisibility() {
        if (document.visibilityState === 'visible') refresh()
    }

    onMounted(() => {
        refresh()
        schedule()
        document.addEventListener('visibilitychange', onVisibility)
    })

    onBeforeUnmount(() => {
        stop()
        document.removeEventListener('visibilitychange', onVisibility)
    })

    return { viewers, network, loading, refresh }
}
