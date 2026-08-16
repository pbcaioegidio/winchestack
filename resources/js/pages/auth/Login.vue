<script setup>
import { useForm, Head } from '@inertiajs/vue3'
import { Router, LoaderCircle, Eye, EyeOff } from '@lucide/vue'
import { ref } from 'vue'
import ThemeToggle from '@/components/ThemeToggle.vue'

defineProps({
    status: String,
    canResetPassword: Boolean,
})

const showPassword = ref(false)

const form = useForm({
    username: '',
    password: '',
    remember: true,
})

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="" />

    <div class="min-h-dvh flex items-center justify-center px-4 py-10 relative overflow-hidden bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <!-- alternador de tema -->
        <div class="absolute top-4 right-4">
            <ThemeToggle />
        </div>

        <div class="relative w-full max-w-sm">
            <!-- marca -->
            <div class="flex flex-col items-center mb-8">
                <div class="grid place-items-center h-14 w-14 rounded-2xl bg-indigo-500/15 ring-1 ring-indigo-400/30 mb-4">
                    <Router class="h-7 w-7 text-indigo-500 dark:text-indigo-400" />
                </div>
                <h1 class="text-xl font-semibold tracking-tight">Winchestack</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Entre para acessar o painel</p>
            </div>

            <!-- card -->
            <form
                @submit.prevent="submit"
                class="rounded-2xl border p-6 shadow-xl space-y-5 border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.03] dark:backdrop-blur-sm dark:shadow-2xl"
            >
                <div
                    v-if="status"
                    class="rounded-lg px-3 py-2 text-sm bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-400/20 dark:text-emerald-300"
                >
                    {{ status }}
                </div>

                <div class="space-y-1.5">
                    <label for="username" class="text-sm font-medium text-slate-700 dark:text-slate-300">Usuário</label>
                    <input
                        id="username"
                        v-model="form.username"
                        type="text"
                        autocomplete="username"
                        autofocus
                        required
                        class="w-full rounded-xl px-3.5 py-2.5 text-sm outline-none transition border bg-white border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 dark:bg-slate-900/70 dark:border-white/10 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-indigo-400/60"
                        placeholder="seu usuário"
                    />
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="text-sm font-medium text-slate-700 dark:text-slate-300">Senha</label>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            required
                            class="w-full rounded-xl px-3.5 py-2.5 pr-11 text-sm outline-none transition border bg-white border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 dark:bg-slate-900/70 dark:border-white/10 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-indigo-400/60"
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 grid w-11 place-items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'"
                        >
                            <EyeOff v-if="showPassword" class="h-4.5 w-4.5" />
                            <Eye v-else class="h-4.5 w-4.5" />
                        </button>
                    </div>
                </div>

                <p v-if="form.errors.username" class="text-sm text-rose-500 dark:text-rose-400">
                    {{ form.errors.username }}
                </p>

                <label class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 select-none">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 dark:border-white/20 bg-white dark:bg-slate-900 text-indigo-600 focus:ring-indigo-500/40"
                    />
                    Manter conectado
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 px-4 py-2.5 text-sm font-semibold text-white transition disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ form.processing ? 'Entrando...' : 'Entrar' }}
                </button>
            </form>

            <p class="text-center text-xs text-slate-400 dark:text-slate-600 mt-6">
                Winchestack · monitoramento de acessos
            </p>
        </div>
    </div>
</template>
