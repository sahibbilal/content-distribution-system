<template>
    <div>
        <PageHeader title="Platforms" description="Connect and manage the accounts you publish to." />

        <div v-if="loading" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="n in 4" :key="n" class="card h-52 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="platform in availablePlatforms"
                :key="platform.type"
                class="card group flex cursor-pointer flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-pop focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                role="button"
                tabindex="0"
                @click="goToPlatform(platform.type)"
                @keyup.enter="goToPlatform(platform.type)"
            >
                <div class="flex items-start justify-between">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 ring-1 ring-slate-900/5">
                        <PlatformIcon :type="platform.type" :size="28" />
                    </span>
                    <span v-if="isConnected(platform.type)" class="badge badge-success">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Connected
                    </span>
                    <span v-else class="badge badge-neutral">
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Not connected
                    </span>
                </div>
                <h3 class="mt-4 text-lg font-semibold text-slate-900">{{ platform.name }}</h3>
                <p class="mt-1 flex-1 text-sm text-slate-500">{{ platform.description }}</p>
                <div class="mt-5 flex items-center gap-1 text-sm font-semibold text-brand-700 group-hover:text-brand-800">
                    {{ isConnected(platform.type) ? 'Manage' : 'Connect' }}
                    <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { ArrowRight } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import PlatformIcon from '../components/PlatformIcon.vue';

export default {
    name: 'Platforms',
    components: { ArrowRight, PageHeader, PlatformIcon },
    setup() {
        const router = useRouter();
        const loading = ref(true);
        const connectedPlatforms = ref([]);

        const availablePlatforms = [
            {
                type: 'linkedin',
                name: 'LinkedIn',
                description: 'Professional networking and content sharing',
            },
            {
                type: 'facebook',
                name: 'Facebook',
                description: 'Social media platform for sharing content',
            },
            {
                type: 'youtube',
                name: 'YouTube',
                description: 'Video sharing and streaming platform',
            },
            {
                type: 'tiktok',
                name: 'TikTok',
                description: 'Short-form video content platform',
            },
            {
                type: 'twitter',
                name: 'X (Twitter)',
                description: 'Short text posts (tweets)',
            },
            {
                type: 'instagram',
                name: 'Instagram',
                description: 'Photos and Reels for Business and Creator accounts',
            },
        ];

        const loadPlatforms = async () => {
            try {
                const response = await axios.get('/platforms');
                connectedPlatforms.value = response.data;
            } catch (error) {
                console.error('Error loading platforms:', error);
            } finally {
                loading.value = false;
            }
        };

        const isConnected = (platformType) => {
            return connectedPlatforms.value.some(p => p.platform_type === platformType && p.is_active && p.credentials_valid);
        };

        const goToPlatform = (platformType) => {
            router.push(`/platforms/${platformType}`);
        };

        onMounted(loadPlatforms);

        return {
            loading,
            availablePlatforms,
            isConnected,
            goToPlatform,
        };
    },
};
</script>
