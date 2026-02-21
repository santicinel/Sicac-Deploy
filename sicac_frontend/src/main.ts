import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import {createPinia} from "pinia"
const app = createApp(App);
const pinia = createPinia();

app.use(pinia);

import router from '@/router/index.ts'
app.use(router);

app.mount('#app');
