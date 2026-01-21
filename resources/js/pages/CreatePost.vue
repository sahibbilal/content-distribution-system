<template>
    <div class="create-post">
        <h1>Create Post</h1>
        
        <form @submit.prevent="handleSubmit" class="post-form">
            <div class="form-group">
                <label>Content</label>
                <textarea v-model="form.content" rows="6" required placeholder="What would you like to post?"></textarea>
            </div>

            <div class="form-group">
                <label>Select Platforms</label>
                <div v-if="loadingPlatforms" class="loading">Loading platforms...</div>
                <div v-else-if="platforms.length === 0" class="warning">
                    No platforms connected. <router-link to="/settings">Connect platforms</router-link>
                </div>
                <div v-else class="platforms-list">
                    <label v-for="platform in platforms" :key="platform.id" class="platform-checkbox">
                        <input type="checkbox" :value="platform.id" v-model="form.platforms" />
                        <span>{{ platform.platform_type }}</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>Media Files (Optional)</label>
                <input 
                    type="file" 
                    @change="handleFileUpload" 
                    multiple 
                    :accept="getFileAcceptTypes()" 
                />
                <small v-if="hasKagglePlatform" style="color: #666; font-size: 0.85rem; display: block; margin-top: 0.25rem;">
                    💡 Kaggle accepts: CSV, JSON, Jupyter notebooks (.ipynb), ZIP, Parquet, Excel, and other dataset formats
                    <span v-if="!isKaggleSelected" style="color: #856404;"> (Select Kaggle platform to upload datasets)</span>
                </small>
                <div v-if="uploadedMedia.length > 0" class="uploaded-media">
                    <div v-for="media in uploadedMedia" :key="media.id" class="media-item">
                        <span>{{ media.filename }}</span>
                        <button type="button" @click="removeMedia(media.id)" class="btn-remove">Remove</button>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" v-model="form.schedule" />
                    Schedule for later
                </label>
                <input v-if="form.schedule" v-model="form.scheduled_at" type="datetime-local" required />
            </div>

            <button type="submit" class="btn-primary" :disabled="loading || form.platforms.length === 0">
                {{ loading ? 'Publishing...' : (form.schedule ? 'Schedule Post' : 'Publish Now') }}
            </button>

            <div v-if="error" class="error-message">{{ error }}</div>
            <div v-if="success" class="success-message">{{ success }}</div>
        </form>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

export default {
    name: 'CreatePost',
    setup() {
        const router = useRouter();
        const form = ref({
            content: '',
            platforms: [],
            media: [],
            schedule: false,
            scheduled_at: '',
        });
        const platforms = ref([]);
        const uploadedMedia = ref([]);
        const loading = ref(false);
        const loadingPlatforms = ref(true);
        const error = ref('');
        const success = ref('');

        const loadPlatforms = async () => {
            try {
                const response = await axios.get('/platforms');
                platforms.value = response.data.filter(p => p.is_active);
            } catch (error) {
                console.error('Error loading platforms:', error);
            } finally {
                loadingPlatforms.value = false;
            }
        };

        const isKaggleSelected = computed(() => {
            const selectedPlatforms = platforms.value.filter(p => form.value.platforms.includes(p.id));
            return selectedPlatforms.some(p => p.platform_type === 'kaggle');
        });

        const hasKagglePlatform = computed(() => {
            return platforms.value.some(p => p.platform_type === 'kaggle');
        });

        const getFileAcceptTypes = () => {
            // If Kaggle platform is available, always allow Kaggle file types
            // This allows users to upload Kaggle files even if they haven't selected Kaggle yet
            if (hasKagglePlatform.value) {
                // Kaggle accepts various dataset file types
                return '.csv,.json,.jsonl,.ipynb,.zip,.tar,.gz,.parquet,.xlsx,.xls,.tsv,.txt,.sql,.db,.sqlite,.h5,.hdf5,.pkl,.pickle,.feather,.arrow,.avro,.orc,.xml,.html,image/*,video/*';
            }
            // Default: images, videos, and common dataset formats
            return 'image/*,video/*,.csv,.json,.zip,.xlsx,.xls';
        };

        const handleFileUpload = async (event) => {
            const files = Array.from(event.target.files);
            loading.value = true;
            error.value = '';

            try {
                // Determine platform for file validation
                // Check if Kaggle is selected in the platforms
                const selectedPlatforms = platforms.value.filter(p => form.value.platforms.includes(p.id));
                const hasKaggle = selectedPlatforms.some(p => p.platform_type === 'kaggle');
                const platformType = hasKaggle ? 'kaggle' : null;

                for (const file of files) {
                    const formData = new FormData();
                    formData.append('file', file);
                    
                    // Pass platform info for file type validation if Kaggle is selected
                    if (platformType) {
                        formData.append('platform', platformType);
                    }
                    
                    const response = await axios.post('/media/upload', formData, {
                        // Don't set Content-Type - let axios/browser set it automatically with boundary
                        headers: {
                            // Remove Content-Type to let browser set it with boundary
                        },
                        timeout: 120000, // 2 minutes for large file uploads
                        onUploadProgress: (progressEvent) => {
                            if (progressEvent.total) {
                                const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                                console.log(`Upload progress: ${percentCompleted}%`);
                            }
                        },
                    });
                    
                    uploadedMedia.value.push(response.data);
                    form.value.media.push(response.data.id);
                }
            } catch (err) {
                const errorData = err.response?.data || {};
                let errorMsg = errorData.error || errorData.message || err.message;
                
                if (errorData.allowed_types) {
                    errorMsg += '\n\nAllowed file types: ' + errorData.allowed_types.join(', ');
                }
                
                error.value = errorMsg;
                console.error('File upload error:', errorData);
            } finally {
                loading.value = false;
            }
        };

        const removeMedia = (mediaId) => {
            uploadedMedia.value = uploadedMedia.value.filter(m => m.id !== mediaId);
            form.value.media = form.value.media.filter(id => id !== mediaId);
        };

        const handleSubmit = async () => {
            if (form.value.platforms.length === 0) {
                error.value = 'Please select at least one platform';
                return;
            }

            loading.value = true;
            error.value = '';
            success.value = '';

            try {
                const payload = {
                    content: form.value.content,
                    platforms: form.value.platforms,
                    media: form.value.media,
                };

                if (form.value.schedule) {
                    payload.scheduled_at = new Date(form.value.scheduled_at).toISOString();
                }

                const response = await axios.post('/posts', payload);
                
                // Check if there's a warning about some platforms failing
                if (response.data.warning) {
                    // Some platforms failed but post was saved
                    error.value = response.data.warning + '. Check platform statuses in Dashboard.';
                    // Don't redirect - let user see the error
                    return;
                }
                
                // Check if response indicates failure (status 422 or error field)
                if (response.status === 422 || response.data.error) {
                    // All platforms failed - post was not saved
                    const errorMsg = response.data.message || response.data.error || 'Failed to publish post to all platforms';
                    error.value = errorMsg;
                    
                    // Show detailed platform errors if available
                    if (response.data.platform_statuses) {
                        const platformErrors = Object.entries(response.data.platform_statuses)
                            .map(([platformId, status]) => {
                                const platformType = status.platform || 'Unknown';
                                const error = status.error || status.message || 'Failed';
                                return `${platformType}: ${error}`;
                            })
                            .join('\n');
                        error.value += '\n\n' + platformErrors;
                    }
                    
                    // Don't redirect - stay on page so user can fix and retry
                    return;
                }
                
                // Success - all platforms published successfully
                success.value = form.value.schedule ? 'Post scheduled successfully!' : 'Post published successfully!';
                
                // Only redirect on complete success
                setTimeout(() => {
                    router.push('/');
                }, 2000);
            } catch (err) {
                // Handle HTTP errors (422, 500, etc.)
                const errorData = err.response?.data || {};
                let errorMsg = errorData.message || errorData.error || 'Failed to create post';
                
                // Add detailed platform errors if available
                if (errorData.platform_statuses) {
                    const platformErrors = Object.entries(errorData.platform_statuses)
                        .map(([platformId, status]) => {
                            const platformType = status.platform || 'Unknown';
                            const error = status.error || status.message || 'Failed';
                            return `${platformType}: ${error}`;
                        })
                        .join('\n');
                    errorMsg += '\n\nPlatform Errors:\n' + platformErrors;
                }
                
                error.value = errorMsg;
                // Don't redirect on error - stay on page
            } finally {
                loading.value = false;
            }
        };

        onMounted(loadPlatforms);

        return {
            form,
            platforms,
            uploadedMedia,
            loading,
            loadingPlatforms,
            error,
            success,
            isKaggleSelected,
            hasKagglePlatform,
            getFileAcceptTypes,
            handleFileUpload,
            removeMedia,
            handleSubmit,
        };
    },
};
</script>

<style scoped>
.create-post {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.create-post h1 {
    margin-bottom: 2rem;
    color: #333;
}

.post-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 500;
    color: #555;
}

.form-group textarea,
.form-group input[type="text"],
.form-group input[type="datetime-local"],
.form-group input[type="file"] {
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
    font-family: inherit;
}

.form-group textarea {
    resize: vertical;
}

.platforms-list {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.platform-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.platform-checkbox:hover {
    background-color: #f5f5f5;
}

.platform-checkbox input[type="checkbox"] {
    cursor: pointer;
}

.uploaded-media {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.media-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: #f5f5f5;
    border-radius: 4px;
}

.btn-remove {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
}

.btn-primary {
    padding: 0.75rem 1.5rem;
    background-color: #667eea;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-primary:hover:not(:disabled) {
    background-color: #5568d3;
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.error-message {
    padding: 0.75rem;
    background-color: #fee;
    color: #c33;
    border-radius: 4px;
    white-space: pre-line;
    max-height: 400px;
    overflow-y: auto;
}

.success-message {
    padding: 0.75rem;
    background-color: #dfd;
    color: #3c3;
    border-radius: 4px;
}

.warning {
    padding: 1rem;
    background-color: #fff3cd;
    color: #856404;
    border-radius: 4px;
}

.warning a {
    color: #667eea;
    text-decoration: none;
}
</style>

