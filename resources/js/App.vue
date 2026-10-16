<template>
    <div id="app" class="min-h-screen">
        <FeedbackHost />

        <div v-if="isAuthenticated" class="min-h-screen">
            <!-- Mobile backdrop -->
            <div v-if="!sidebarCollapsed" class="fixed inset-0 z-30 bg-slate-900/40 backdrop-blur-sm md:hidden" @click="toggleSidebar"></div>

            <!-- Sidebar -->
            <aside
                class="fixed inset-y-0 left-0 z-40 flex flex-col border-r border-slate-200 bg-white transition-all duration-200"
                :class="sidebarCollapsed ? '-translate-x-full md:w-[72px] md:translate-x-0' : 'w-64 translate-x-0'"
            >
                <div class="flex h-16 shrink-0 items-center border-b border-slate-100 px-4" :class="sidebarCollapsed ? 'md:justify-center' : 'justify-between'">
                    <BrandLogo :size="40" :class="{ 'md:hidden': sidebarCollapsed }" />
                    <button @click="toggleSidebar" class="btn btn-ghost p-2!" aria-label="Toggle sidebar">
                        <PanelLeftClose class="h-5 w-5 transition-transform" :class="{ 'rotate-180': sidebarCollapsed }" />
                    </button>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                    <template v-for="item in navItems" :key="item.label">
                        <!-- A group that opens and closes (Posts: all posts + create post) -->
                        <div v-if="item.children">
                            <button type="button" class="nav-link w-full" :class="{ 'md:justify-center md:px-0': sidebarCollapsed, 'bg-brand-50! text-brand-700!': groupActive(item) && !groupOpen(item) }"
                                :title="item.label" :aria-expanded="groupOpen(item)" @click="toggleGroup(item)">
                                <component :is="item.icon" class="h-5 w-5 shrink-0" />
                                <span class="flex-1 text-left" :class="{ 'md:hidden': sidebarCollapsed }">{{ item.label }}</span>
                                <ChevronDown class="h-4 w-4 shrink-0 transition-transform" :class="[groupOpen(item) ? 'rotate-180' : '', { 'md:hidden': sidebarCollapsed }]" />
                            </button>
                            <div v-show="groupOpen(item) && !sidebarCollapsed" class="mt-1 space-y-1 border-l border-slate-200 pl-3 ml-5">
                                <router-link v-for="child in item.children" :key="child.to" :to="child.to" class="nav-link py-2!" exact-active-class="router-link-active" @click="closeSidebarMobile">
                                    <component :is="child.icon" class="h-4 w-4 shrink-0" />
                                    <span>{{ child.label }}</span>
                                </router-link>
                            </div>
                        </div>
                        <router-link v-else :to="item.to" class="nav-link"
                            :class="{ 'md:justify-center md:px-0': sidebarCollapsed }" :title="item.label" @click="closeSidebarMobile">
                            <component :is="item.icon" class="h-5 w-5 shrink-0" />
                            <span :class="{ 'md:hidden': sidebarCollapsed }">{{ item.label }}</span>
                        </router-link>
                    </template>
                    <router-link v-if="isAdmin" to="/admin" class="nav-link" :class="{ 'md:justify-center md:px-0': sidebarCollapsed }"
                        title="Admin" @click="closeSidebarMobile">
                        <ShieldCheck class="h-5 w-5 shrink-0" />
                        <span :class="{ 'md:hidden': sidebarCollapsed }">Admin</span>
                    </router-link>
                </nav>

                <div class="shrink-0 border-t border-slate-100 p-3">
                    <router-link to="/profile" class="flex items-center gap-3 rounded-lg p-2 transition hover:bg-slate-50" :class="{ 'md:justify-center': sidebarCollapsed }"
                        title="Edit your profile" @click="closeSidebarMobile">
                        <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover" />
                        <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">
                            {{ initial }}
                        </div>
                        <div class="min-w-0 flex-1" :class="{ 'md:hidden': sidebarCollapsed }">
                            <p class="truncate text-sm font-medium text-slate-900">{{ user?.name || 'Account' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ user?.email }}</p>
                        </div>
                    </router-link>
                    <button @click="logout" class="nav-link mt-1 w-full hover:bg-red-50! hover:text-red-700!"
                        :class="{ 'md:justify-center md:px-0': sidebarCollapsed }" title="Logout">
                        <LogOut class="h-5 w-5 shrink-0" />
                        <span :class="{ 'md:hidden': sidebarCollapsed }">Logout</span>
                    </button>
                </div>
            </aside>

            <!-- Main -->
            <div class="transition-[padding] duration-200" :class="sidebarCollapsed ? 'md:pl-[72px]' : 'md:pl-64'">
                <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-slate-200 bg-white/80 px-4 backdrop-blur md:hidden">
                    <button @click="toggleSidebar" class="btn btn-ghost p-2!" aria-label="Open menu">
                        <Menu class="h-5 w-5" />
                    </button>
                    <BrandLogo :size="34" />
                </header>
                <main class="w-full px-4 py-6 lg:py-8">
                    <router-view />
                </main>
            </div>
        </div>
        <div v-else>
            <router-view />
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

import { CalendarDays, ChevronDown, Clapperboard, Images, LayoutDashboard, Link2, ListChecks, LogOut, Menu, PanelLeftClose, PenSquare, Settings, ShieldCheck, Sparkles } from 'lucide-vue-next';
import BrandLogo from './components/BrandLogo.vue';
import FeedbackHost from './components/FeedbackHost.vue';

export default {
    components: { BrandLogo, FeedbackHost, ChevronDown, ShieldCheck, LogOut, Menu, PanelLeftClose },
    name: 'App',
    setup() {
        const router = useRouter();
        // localStorage isn't reactive, so re-check the token after every navigation
        const readIsAdmin = () => {
            try {
                return !!JSON.parse(localStorage.getItem('user') || 'null')?.is_admin;
            } catch (e) {
                return false;
            }
        };
        const readUser = () => {
            try {
                return JSON.parse(localStorage.getItem('user') || 'null');
            } catch (e) {
                return null;
            }
        };
        const isAuthenticated = ref(!!localStorage.getItem('token'));
        const isAdmin = ref(readIsAdmin());
        const user = ref(readUser());
        const initial = computed(() => (user.value?.name || user.value?.email || '?').charAt(0).toUpperCase());
        const navItems = [
            { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
            {
                label: 'Posts', icon: ListChecks, base: ['/posts', '/create-post'],
                children: [
                    { to: '/posts', label: 'All posts', icon: ListChecks },
                    { to: '/create-post', label: 'Create post', icon: PenSquare },
                ],
            },
            { to: '/ai-assistant', label: 'AI assistant', icon: Sparkles },
            { to: '/episodes', label: 'Video episodes', icon: Clapperboard },
            { to: '/calendar', label: 'Calendar', icon: CalendarDays },
            { to: '/media', label: 'Media', icon: Images },
            { to: '/platforms', label: 'Platforms', icon: Link2 },
            { to: '/settings', label: 'Settings', icon: Settings },
        ];
        // Groups open by hand, and open by themselves when you are on one of their pages
        const opened = ref({});
        const groupActive = (item) => item.base.some(b => router.currentRoute.value.path === b || router.currentRoute.value.path.startsWith(b + '/'));
        const groupOpen = (item) => opened.value[item.label] ?? true; // open by default, the arrow hides it
        const toggleGroup = (item) => {
            if (sidebarCollapsed.value && window.innerWidth >= 768) {
                // icon-only sidebar: open the list page instead
                router.push(item.children[0].to);
                return;
            }
            opened.value = { ...opened.value, [item.label]: !groupOpen(item) };
        };
        router.afterEach(() => {
            isAuthenticated.value = !!localStorage.getItem('token');
            isAdmin.value = readIsAdmin();
            user.value = readUser();
        });

        // The profile page changes the stored user without navigating
        window.addEventListener('user-updated', () => {
            user.value = readUser();
        });

        // Refresh the stored user so admin promotions/demotions are picked up
        onMounted(async () => {
            if (!localStorage.getItem('token')) return;
            try {
                const { data } = await axios.get('/auth/me');
                localStorage.setItem('user', JSON.stringify(data));
                isAdmin.value = !!data.is_admin;
                user.value = data;
            } catch (e) {
                // a 401 is handled globally by the axios interceptor
            }
        });
        const sidebarCollapsed = ref(window.innerWidth < 768);

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
                localStorage.removeItem('user');
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
            isAdmin,
            user,
            initial,
            navItems,
            groupActive,
            groupOpen,
            toggleGroup,
            sidebarCollapsed,
            toggleSidebar,
            closeSidebarMobile,
            logout,
        };
    },
};
</script>
