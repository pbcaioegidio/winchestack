import { ref, watch } from 'vue'

/**
 * Tema da interface: 'light' | 'dark' | 'system'.
 * Persistido no localStorage e aplicado adicionando/removendo a classe `dark`
 * no <html>. Em 'system', segue o prefers-color-scheme e reage a mudanças.
 * Singleton (estado compartilhado entre todos os componentes).
 */
const KEY = 'winchestack:theme'
const theme = ref(localStorage.getItem(KEY) || 'system')

const mq = window.matchMedia('(prefers-color-scheme: dark)')

function resolveDark(t) {
    return t === 'dark' || (t === 'system' && mq.matches)
}

function apply(t) {
    document.documentElement.classList.toggle('dark', resolveDark(t))
}

apply(theme.value)

mq.addEventListener?.('change', () => {
    if (theme.value === 'system') apply('system')
})

watch(theme, (t) => {
    localStorage.setItem(KEY, t)
    apply(t)
})

export function useTheme() {
    const setTheme = (t) => { theme.value = t }
    // cicla claro → escuro → sistema → claro
    const cycle = () => {
        theme.value = theme.value === 'light' ? 'dark'
            : theme.value === 'dark' ? 'system'
            : 'light'
    }
    return { theme, setTheme, cycle }
}
