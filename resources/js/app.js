import './bootstrap';
import { createApp } from 'vue';

// Expor Vue globalmente para uso em scripts inline
window.Vue = { createApp };

// Importar componentes Vue (serão criados posteriormente)
// import UserManager from './components/UserManager.vue';

// Criar aplicação Vue
const app = createApp({});

// Registrar componentes
// app.component('user-manager', UserManager);

// Montar aplicação
app.mount('#app');
