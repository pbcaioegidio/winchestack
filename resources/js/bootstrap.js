import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Pede JSON: assim, quando a sessão expira, a API responde 401 (em vez de
// redirecionar pro HTML do login), e o interceptor abaixo trata.
window.axios.defaults.headers.common['Accept'] = 'application/json';

// Sessão expirada / não autenticado em chamada XHR → volta pra tela de login.
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    },
);
