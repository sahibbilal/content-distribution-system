<template>
    <div class="dashboard">
        <h1>Dashboard</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Posts</h3>
                <p class="stat-number">{{ posts.length }}</p>
            </div>
            <div class="stat-card">
                <h3>Scheduled Posts</h3>
                <p class="stat-number">{{ schedules.length }}</p>
            </div>
            <div class="stat-card">
                <h3>Connected Platforms</h3>
                <p class="stat-number">{{ platforms.length }}</p>
            </div>
        </div>

        <div class="section">
            <h2>Recent Posts</h2>
            <div v-if="loading" class="loading">Loading...</div>
            <div v-else-if="posts.length === 0" class="empty-state">
                No posts yet. <router-link to="/create-post">Create your first post</router-link>
            </div>
            <div v-else class="posts-list">
                <div v-for="post in posts" :key="post.id" class="post-card">
                    <div class="post-content">{{ post.content }}</div>
                    <div class="post-meta">
                        <span class="post-status" :class="post.status">{{ post.status }}</span>
                        <span class="post-date">{{ formatDate(post.created_at) }}</span>
                    </div>
                    <div v-if="post.platform_statuses" class="platform-statuses">
                        <div v-for="(status, platformId) in post.platform_statuses" :key="platformId" 
                              class="platform-status-item">
                            <span class="platform-status" :class="status.status">
                                {{ getPlatformName(platformId) }}: {{ status.status }}
                            </span>
                            <a v-if="status.url" :href="status.url" target="_blank" class="platform-link" 
                               :title="status.url">
                                🔗 View on {{ getPlatformName(platformId) }}
                            </a>
                            <span v-if="status.message" class="platform-message">{{ status.message }}</span>
                            <span v-if="status.error && status.status === 'failed'" class="platform-error">{{ status.error }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Scheduled Posts</h2>
            <div v-if="schedules.length === 0" class="empty-state">No scheduled posts</div>
            <div v-else class="schedules-list">
                <div v-for="schedule in schedules" :key="schedule.id" class="schedule-card">
                    <div class="schedule-content">{{ schedule.post.content }}</div>
                    <div class="schedule-meta">
                        <span>Scheduled for: {{ formatDate(schedule.scheduled_at) }}</span>
                        <span class="schedule-status" :class="schedule.status">{{ schedule.status }}</span>
                        <button @click="deleteSchedule(schedule.id)" class="btn-danger btn-sm">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'Dashboard',
    setup() {
        const posts = ref([]);
        const schedules = ref([]);
        const platforms = ref([]);
        const loading = ref(true);

        const loadData = async () => {
            try {
                const [postsRes, schedulesRes, platformsRes] = await Promise.all([
                    axios.get('/posts'),
                    axios.get('/schedules'),
                    axios.get('/platforms'),
                ]);
                posts.value = postsRes.data.data || postsRes.data;
                schedules.value = schedulesRes.data.data || schedulesRes.data;
                platforms.value = platformsRes.data;
            } catch (error) {
                console.error('Error loading dashboard data:', error);
            } finally {
                loading.value = false;
            }
        };

        const deleteSchedule = async (id) => {
            if (!confirm('Are you sure you want to delete this schedule?')) return;
            
            try {
                await axios.delete(`/schedules/${id}`);
                schedules.value = schedules.value.filter(s => s.id !== id);
            } catch (error) {
                alert('Failed to delete schedule');
            }
        };

        const formatDate = (date) => {
            return new Date(date).toLocaleString();
        };

        const getPlatformName = (platformId) => {
            const platform = platforms.value.find(p => p.id === parseInt(platformId));
            if (platform) {
                return platform.platform_type.charAt(0).toUpperCase() + platform.platform_type.slice(1);
            }
            return `Platform ${platformId}`;
        };

        onMounted(loadData);

        return {
            posts,
            schedules,
            platforms,
            loading,
            deleteSchedule,
            formatDate,
            getPlatformName,
        };
    },
};
</script>

<style scoped>
.dashboard {
    padding: 2rem;
}

.dashboard h1 {
    margin-bottom: 2rem;
    color: #333;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-card h3 {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #667eea;
}

.section {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.section h2 {
    margin-bottom: 1rem;
    color: #333;
}

.loading, .empty-state {
    text-align: center;
    padding: 2rem;
    color: #666;
}

.posts-list, .schedules-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.post-card, .schedule-card {
    padding: 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f9f9f9;
}

.post-content, .schedule-content {
    margin-bottom: 0.5rem;
    color: #333;
}

.post-meta, .schedule-meta {
    display: flex;
    gap: 1rem;
    align-items: center;
    font-size: 0.9rem;
    color: #666;
}

.post-status, .schedule-status {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.post-status.published, .schedule-status.completed {
    background: #d4edda;
    color: #155724;
}

.post-status.scheduled, .schedule-status.pending {
    background: #fff3cd;
    color: #856404;
}

.post-status.failed, .schedule-status.failed {
    background: #f8d7da;
    color: #721c24;
}

.platform-statuses {
    margin-top: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.platform-status-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.platform-status {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    background: #e9ecef;
}

.platform-status.published {
    background: #d4edda;
    color: #155724;
}

.platform-status.failed {
    background: #f8d7da;
    color: #721c24;
}

.platform-link {
    font-size: 0.8rem;
    color: #667eea;
    text-decoration: none;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.platform-link:hover {
    background-color: #f0f0f0;
    text-decoration: underline;
}

.platform-message {
    font-size: 0.75rem;
    color: #666;
    font-style: italic;
}

.platform-error {
    font-size: 0.75rem;
    color: #dc3545;
    font-weight: 500;
    display: block;
    margin-top: 0.25rem;
    padding: 0.25rem 0.5rem;
    background-color: #f8d7da;
    border-radius: 4px;
    border-left: 3px solid #dc3545;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}
</style>

