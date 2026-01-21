import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import axios from 'axios';
import App from './App.vue';
import Login from './pages/Login.vue';
import Register from './pages/Register.vue';
import Dashboard from './pages/Dashboard.vue';
import CreatePost from './pages/CreatePost.vue';
import Settings from './pages/Settings.vue';
import Platforms from './pages/Platforms.vue';
import PlatformDetail from './pages/PlatformDetail.vue';

// Configure axios
// Determine API base URL based on environment
let apiBaseURL;
if (import.meta.env.VITE_API_URL) {
    apiBaseURL = import.meta.env.VITE_API_URL;
} else if (window.location.hostname === 'localhost' && window.location.port === '5173') {
    // Use Vite proxy when running dev server
    apiBaseURL = '/api';
} else if (window.location.hostname.includes('ddev.site')) {
    // Use relative path when on DDEV site
    apiBaseURL = '/api';
} else {
    // Fallback to DDEV site
    apiBaseURL = 'https://content-distribution-system.ddev.site/api';
}

axios.defaults.baseURL = apiBaseURL;
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.withCredentials = true;
axios.defaults.timeout = 30000; // 30 second timeout

// Request interceptor to set Content-Type only for non-form-data requests
axios.interceptors.request.use(
    config => {
        // Only set Content-Type if it's not FormData
        if (!(config.data instanceof FormData)) {
            config.headers['Content-Type'] = 'application/json';
        }
        // Add token to requests if available
        const token = localStorage.getItem('token');
        if (token) {
            config.headers['Authorization'] = `Bearer ${token}`;
        }
        return config;
    },
    error => {
        return Promise.reject(error);
    }
);

// Add token to requests if available (initial load)
const token = localStorage.getItem('token');
if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
}

// Response interceptor to handle token refresh and errors
axios.interceptors.response.use(
    response => response,
    error => {
        // Handle network errors
        if (!error.response) {
            if (error.code === 'ERR_NETWORK' || error.message.includes('Network Error')) {
                console.error('Network error:', error);
                // Try to use fallback URL if proxy fails
                if (apiBaseURL === '/api' && window.location.hostname === 'localhost') {
                    console.warn('Proxy failed, trying direct connection to DDEV site');
                    // Don't automatically retry, just log the error
                }
            }
        }
        
        if (error.response?.status === 401) {
            localStorage.removeItem('token');
            delete axios.defaults.headers.common['Authorization'];
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

// Router configuration
const routes = [
    { path: '/login', component: Login, meta: { requiresGuest: true } },
    { path: '/register', component: Register, meta: { requiresGuest: true } },
    { path: '/', component: Dashboard, meta: { requiresAuth: true } },
    { path: '/create-post', component: CreatePost, meta: { requiresAuth: true } },
    { path: '/settings', component: Settings, meta: { requiresAuth: true } },
    { path: '/platforms', component: Platforms, meta: { requiresAuth: true } },
    { path: '/platforms/:platform', component: PlatformDetail, meta: { requiresAuth: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Navigation guard
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token');
    const isAuthenticated = !!token;

    if (to.meta.requiresAuth && !isAuthenticated) {
        next('/login');
    } else if (to.meta.requiresGuest && isAuthenticated) {
        next('/');
    } else {
        next();
    }
});

// Create Vue app
const app = createApp(App);
app.use(router);
app.mount('#app');

