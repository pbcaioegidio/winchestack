import { ref, onMounted, onBeforeUnmount, readonly } from 'vue'

/**
 * Detecta se devemos renderizar a UI mobile ou a desktop.
 *
 * Regra: largura < 1024px => mobile (celular / tablet retrato).
 * >= 1024px => desktop (notebook, monitor). Reage a resize e mudança de
 * orientação. É um singleton (estado compartilhado entre todos os componentes
 * que usam o composable).
 */
const DESKTOP_MIN = 1024

function compute() {
    if (typeof window === 'undefined') return false
    return window.innerWidth < DESKTOP_MIN
}

const isMobile = ref(compute())
let mounts = 0

function onResize() {
    isMobile.value = compute()
}

export function useDevice() {
    onMounted(() => {
        isMobile.value = compute()
        if (mounts === 0) {
            window.addEventListener('resize', onResize)
            window.addEventListener('orientationchange', onResize)
        }
        mounts++
    })

    onBeforeUnmount(() => {
        mounts = Math.max(0, mounts - 1)
        if (mounts === 0) {
            window.removeEventListener('resize', onResize)
            window.removeEventListener('orientationchange', onResize)
        }
    })

    return { isMobile: readonly(isMobile) }
}
