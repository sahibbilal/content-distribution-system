<template>
    <div id="app">
        <div v-if="isAuthenticated" class="app-layout">
            <!-- Sidebar -->
            <aside class="sidebar" :class="{ 'sidebar-collapsed': sidebarCollapsed }">
                <div class="sidebar-header">
                    <h2 class="sidebar-brand">Content Distribution</h2>
                    <button @click="toggleSidebar" class="sidebar-toggle" aria-label="Toggle sidebar">
                        <span v-if="!sidebarCollapsed">☰</span>
                        <span v-else>☰</span>
                    </button>
                </div>
                <nav class="sidebar-nav">
                    <router-link to="/" class="nav-item" @click="closeSidebarMobile">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Dashboard</span>
                    </router-link>
                    <router-link to="/create-post" class="nav-item" @click="closeSidebarMobile">
                        <span class="nav-icon">✏️</span>
                        <span class="nav-text">Create Post</span>
                    </router-link>
                    <router-link to="/platforms" class="nav-item" @click="closeSidebarMobile">
                        <span class="nav-icon">🔗</span>
                        <span class="nav-text">Platforms</span>
                    </router-link>
                    <router-link to="/settings" class="nav-item" @click="closeSidebarMobile">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-text">Settings</span>
                    </router-link>
                    <div class="nav-divider"></div>
                    <button @click="logout" class="nav-item nav-item-button">
                        <span class="nav-icon">🚪</span>
                        <span class="nav-text">Logout</span>
                    </button>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="main-content" :class="{ 'main-content-expanded': sidebarCollapsed }">
                <button @click="toggleSidebar" class="mobile-menu-toggle" aria-label="Toggle menu">
                    ☰
                </button>
                <router-view />
            </main>
        </div>
        <div v-else class="auth-layout">
            <router-view />
        </div>
    </div>
</template>

<script>
import { computed, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

export default {
    name: 'App',
    setup() {
        const router = useRouter();
        const isAuthenticated = computed(() => !!localStorage.getItem('token'));
        const sidebarCollapsed = ref(false);

        const toggleSidebar = () => {
            sidebarCollapsed.value = !sidebarCollapsed.value;
        };

        const closeSidebarMobile = () => {
            // Close sidebar on mobile after navigation
            if (window.innerWidth < 768) {
                sidebarCollapsed.value = true;
            }
        };

        const logout = async () => {
            try {
                await axios.post('/auth/logout');
            } catch (error) {
                console.error('Logout error:', error);
            } finally {
                localStorage.removeItem('token');
                delete axios.defaults.headers.common['Authorization'];
                router.push('/login');
            }
        };

        // Handle window resize
        onMounted(() => {
            const handleResize = () => {
                if (window.innerWidth < 768) {
                    // On mobile, keep sidebar closed
                    sidebarCollapsed.value = true;
                } else {
                    // On desktop, keep sidebar open
                    sidebarCollapsed.value = false;
                }
            };
            // Initial check
            if (typeof window !== 'undefined') {
                handleResize();
                window.addEventListener('resize', handleResize);
            }
        });

        return {
            isAuthenticated,
            sidebarCollapsed,
            toggleSidebar,
            closeSidebarMobile,
            logout,
        };
    },
};
</script>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background-color: #f5f5f5;
}

/* App Layout */
.app-layout {
    display: flex;
    min-height: 100vh;
}

.auth-layout {
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 260px;
    background-color: #2c3e50;
    color: white;
    display: flex;
    flex-direction: column;
    transition: width 0.3s ease;
    box-shadow: 2px 0 8px rgba(0,0,0,0.1);
    position: fixed;
    height: 100vh;
    z-index: 1000;
    overflow-y: auto;
}

.sidebar-collapsed {
    width: 70px;
}

.sidebar-header {
    padding: 1.5rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-brand {
    font-size: 1.25rem;
    font-weight: bold;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-collapsed .sidebar-brand {
    display: none;
}

.sidebar-toggle {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.25rem;
    transition: transform 0.2s;
}

.sidebar-toggle:hover {
    opacity: 0.8;
}

.sidebar-nav {
    flex: 1;
    padding: 1rem 0;
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 0.875rem 1.5rem;
    color: white;
    text-decoration: none;
    transition: all 0.2s;
    border-left: 3px solid transparent;
    cursor: pointer;
    background: none;
    border-right: none;
    border-top: none;
    border-bottom: none;
    width: 100%;
    text-align: left;
    font-size: 1rem;
}

.nav-item:hover {
    background-color: rgba(255,255,255,0.1);
    border-left-color: #667eea;
}

.nav-item.router-link-active {
    background-color: rgba(102, 126, 234, 0.2);
    border-left-color: #667eea;
    font-weight: 500;
}

.nav-icon {
    font-size: 1.25rem;
    margin-right: 1rem;
    min-width: 24px;
    text-align: center;
}

.sidebar-collapsed .nav-icon {
    margin-right: 0;
}

.nav-text {
    white-space: nowrap;
    overflow: hidden;
}

.sidebar-collapsed .nav-text {
    display: none;
}

.nav-item-button {
    color: #e74c3c;
}

.nav-item-button:hover {
    background-color: rgba(231, 76, 60, 0.1);
    border-left-color: #e74c3c;
}

.nav-divider {
    height: 1px;
    background-color: rgba(255,255,255,0.1);
    margin: 0.5rem 1.5rem;
}

/* Main Content */
.main-content {
    flex: 1;
    margin-left: 260px;
    padding: 2rem;
    transition: margin-left 0.3s ease;
    min-height: 100vh;
    position: relative;
}

.main-content-expanded {
    margin-left: 70px;
}

.mobile-menu-toggle {
    display: none;
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 999;
    background-color: #2c3e50;
    color: white;
    border: none;
    padding: 0.75rem;
    border-radius: 4px;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    transition: background-color 0.2s;
}

.mobile-menu-toggle:hover {
    background-color: #34495e;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 260px;
    }

    .sidebar:not(.sidebar-collapsed) {
        transform: translateX(0);
    }

    .sidebar-collapsed {
        transform: translateX(-100%);
    }

    .main-content {
        margin-left: 0;
        padding: 1rem;
        padding-top: 4rem;
    }

    .main-content-expanded {
        margin-left: 0;
    }

    .mobile-menu-toggle {
        display: block;
    }

    /* Mobile overlay */
    .sidebar:not(.sidebar-collapsed)::after {
        content: '';
        position: fixed;
        top: 0;
        left: 260px;
        right: 0;
        bottom: 0;
        background-color: rgba(0,0,0,0.5);
        z-index: 999;
    }
}

/* Scrollbar styling for sidebar */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.3);
}
</style>

