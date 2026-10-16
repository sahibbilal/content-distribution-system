import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import axios from 'axios';
import '../css/app.css';
import App from './App.vue';
import Login from './pages/Login.vue';
import Register from './pages/Register.vue';
import Home from './pages/Home.vue';
import Terms from './pages/Terms.vue';
import Privacy from './pages/Privacy.vue';
import Admin from './pages/Admin.vue';
import AdminUsers from './pages/AdminUsers.vue';
import AdminAi from './pages/AdminAi.vue';
import AdminEmail from './pages/AdminEmail.vue';
import Dashboard from './pages/Dashboard.vue';
import CreatePost from './pages/CreatePost.vue';
import Settings from './pages/Settings.vue';
import Profile from './pages/Profile.vue';
import Platforms from './pages/Platforms.vue';
import Posts from './pages/Posts.vue';
import PostDetail from './pages/PostDetail.vue';
import Calendar from './pages/Calendar.vue';
import Media from './pages/Media.vue';
import AdminSettings from './pages/AdminSettings.vue';
import AdminPipeline from './pages/AdminPipeline.vue';
import AiAssistant from './pages/AiAssistant.vue';
import DataDeletion from './pages/DataDeletion.vue';
import PlatformDetail from './pages/PlatformDetail.vue';
import Episodes from './pages/Episodes.vue';
import EpisodeDetail from './pages/EpisodeDetail.vue';

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
} else if (window.location.hostname === 'cds.wpcorex.com' || window.location.hostname.includes('wpcorex.com')) {
    // Production environment
    apiBaseURL = 'https://cds.wpcorex.com/api';
} else {
    // Fallback to relative path (works for most cases)
    apiBaseURL = '/api';
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

// A login without "Remember me" ends when the browser session ends
if (localStorage.getItem('token')
    && localStorage.getItem('remember') !== '1'
    && !sessionStorage.getItem('session_active')) {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
}

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
            localStorage.removeItem('user');
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
    { path: '/', component: Home, meta: { requiresGuest: true } },
    { path: '/terms', component: Terms },
    { path: '/privacy', component: Privacy },
    { path: '/data-deletion', component: DataDeletion },
    { path: '/dashboard', component: Dashboard, meta: { requiresAuth: true } },
    { path: '/admin', component: Admin, meta: { requiresAuth: true, requiresAdmin: true } },
    { path: '/admin/users', component: AdminUsers, meta: { requiresAuth: true, requiresAdmin: true } },
    { path: '/admin/ai', component: AdminAi, meta: { requiresAuth: true, requiresAdmin: true } },
    { path: '/admin/email', component: AdminEmail, meta: { requiresAuth: true, requiresAdmin: true } },
    { path: '/admin/settings', component: AdminSettings, meta: { requiresAuth: true, requiresAdmin: true } },
    { path: '/admin/pipeline', component: AdminPipeline, meta: { requiresAuth: true, requiresAdmin: true } },
    { path: '/create-post', component: CreatePost, meta: { requiresAuth: true } },
    { path: '/posts', component: Posts, meta: { requiresAuth: true } },
    { path: '/posts/:id', component: PostDetail, meta: { requiresAuth: true } },
    { path: '/ai-assistant', component: AiAssistant, meta: { requiresAuth: true } },
    { path: '/episodes', component: Episodes, meta: { requiresAuth: true } },
    { path: '/episodes/:id', component: EpisodeDetail, meta: { requiresAuth: true } },
    { path: '/calendar', component: Calendar, meta: { requiresAuth: true } },
    { path: '/media', component: Media, meta: { requiresAuth: true } },
    { path: '/settings', component: Settings, meta: { requiresAuth: true } },
    { path: '/profile', component: Profile, meta: { requiresAuth: true } },
    { path: '/platforms', component: Platforms, meta: { requiresAuth: true } },
    { path: '/platforms/:platform', component: PlatformDetail, meta: { requiresAuth: true } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Navigation guard
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token');
    const isAuthenticated = !!token;

    let isAdmin = false;
    try {
        isAdmin = !!JSON.parse(localStorage.getItem('user') || 'null')?.is_admin;
    } catch (e) {
        // ignore a corrupt stored user
    }

    if (to.meta.requiresAuth && !isAuthenticated) {
        next('/login');
    } else if (to.meta.requiresAdmin && !isAdmin) {
        next('/dashboard');
    } else if (to.meta.requiresGuest && isAuthenticated) {
        next('/dashboard');
    } else {
        next();
    }
});

// Facebook Login Status Check
function checkFacebookLoginStatus() {
    if (typeof FB !== 'undefined') {
        FB.getLoginStatus(function(response) {
            console.log('Facebook login status:', response);
            // If user is connected to Facebook, you can handle it here
            if (response.status === 'connected') {
                // User is logged in to Facebook
                // You can optionally sync with your backend here
                console.log('Facebook user connected:', response.authResponse.userID);
            }
        });
    }
}

// Wait for Facebook SDK to load
window.fbAsyncInit = function() {
    if (typeof FB !== 'undefined') {
        // Check login status when SDK is ready
        checkFacebookLoginStatus();
    }
};

// Create Vue app
const app = createApp(App);
app.use(router);
app.mount('#app');

