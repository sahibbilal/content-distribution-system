<template>
    <div class="platforms-page">
        <h1>Platforms</h1>
        <p class="page-description">Connect and manage your social media platforms</p>

        <div v-if="loading" class="loading">Loading platforms...</div>
        
        <div v-else class="platforms-grid">
            <div 
                v-for="platform in availablePlatforms" 
                :key="platform.type"
                class="platform-card"
                @click="goToPlatform(platform.type)"
            >
                <div class="platform-icon">{{ platform.icon }}</div>
                <h3>{{ platform.name }}</h3>
                <p class="platform-description">{{ platform.description }}</p>
                
                <div class="platform-status">
                    <span v-if="isConnected(platform.type)" class="status-badge connected">
                        ✓ Connected
                    </span>
                    <span v-else class="status-badge disconnected">
                        Not Connected
                    </span>
                </div>
                
                <button class="btn-view-details">
                    {{ isConnected(platform.type) ? 'Manage' : 'Connect' }} →
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

export default {
    name: 'Platforms',
    setup() {
        const router = useRouter();
        const loading = ref(true);
        const connectedPlatforms = ref([]);

        const availablePlatforms = [
            {
                type: 'linkedin',
                name: 'LinkedIn',
                icon: '💼',
                description: 'Professional networking and content sharing',
            },
            {
                type: 'facebook',
                name: 'Facebook',
                icon: '📘',
                description: 'Social media platform for sharing content',
            },
            {
                type: 'youtube',
                name: 'YouTube',
                icon: '📺',
                description: 'Video sharing and streaming platform',
            },
            {
                type: 'tiktok',
                name: 'TikTok',
                icon: '🎵',
                description: 'Short-form video content platform',
            },
            {
                type: 'kaggle',
                name: 'Kaggle',
                icon: '📊',
                description: 'Data science and machine learning platform',
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
            return connectedPlatforms.value.some(p => p.platform_type === platformType && p.is_active);
        };

        const goToPlatform = (platformType) => {
            router.push(`/platforms/${platformType}`);
        };

        onMounted(loadPlatforms);

        return {
            loading,
            availablePlatforms,
            connectedPlatforms,
            isConnected,
            goToPlatform,
        };
    },
};
</script>

<style scoped>
.platforms-page {
    padding: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

.platforms-page h1 {
    margin-bottom: 0.5rem;
    color: #333;
}

.page-description {
    color: #666;
    margin-bottom: 2rem;
}

.loading {
    text-align: center;
    padding: 3rem;
    color: #666;
}

.platforms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}

.platform-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.platform-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    transform: translateY(-2px);
}

.platform-icon {
    font-size: 3rem;
    margin-bottom: 0.5rem;
}

.platform-card h3 {
    margin: 0;
    color: #333;
    font-size: 1.5rem;
}

.platform-description {
    color: #666;
    font-size: 0.9rem;
    margin: 0;
    flex-grow: 1;
}

.platform-status {
    margin: 0.5rem 0;
}

.status-badge {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.status-badge.connected {
    background: #d4edda;
    color: #155724;
}

.status-badge.disconnected {
    background: #f8d7da;
    color: #721c24;
}

.btn-view-details {
    margin-top: auto;
    padding: 0.75rem 1.5rem;
    background-color: #667eea;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
    width: 100%;
}

.btn-view-details:hover {
    background-color: #5568d3;
}
</style>
