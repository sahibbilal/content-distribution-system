<template>
    <div class="settings">
        <h1>Platform Settings</h1>
        
        <div class="platforms-section">
            <h2>Connected Platforms</h2>
            <div v-if="loading" class="loading">Loading...</div>
            <div v-else-if="platforms.length === 0" class="empty-state">No platforms connected</div>
            <div v-else class="platforms-list">
                <div v-for="platform in platforms" :key="platform.id" class="platform-card">
                    <div class="platform-info">
                        <h3>{{ platform.platform_type }}</h3>
                        <span class="platform-status" :class="{ active: platform.is_active }">
                            {{ platform.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <button @click="disconnectPlatform(platform.platform_type)" class="btn-danger">
                        Disconnect
                    </button>
                </div>
            </div>
        </div>

        <div class="connect-section">
            <h2>Connect Platform</h2>
            <form @submit.prevent="handleConnect" class="connect-form">
                <div class="form-group">
                    <label>Platform</label>
                    <select v-model="connectForm.platform_type" required>
                        <option value="">Select a platform</option>
                        <option value="facebook">Facebook</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="youtube">YouTube</option>
                        <option value="tiktok">TikTok</option>
                        <option value="kaggle">Kaggle</option>
                    </select>
                </div>

                <div v-if="connectForm.platform_type === 'facebook'" class="platform-credentials">
                    <div class="form-group">
                        <label>Page ID</label>
                        <input v-model="connectForm.credentials.page_id" type="text" required />
                    </div>
                    <div class="form-group">
                        <label>Access Token</label>
                        <input v-model="connectForm.credentials.access_token" type="text" required />
                    </div>
                    <div class="form-group">
                        <label>App Secret (Optional)</label>
                        <input v-model="connectForm.credentials.app_secret" type="text" />
                    </div>
                </div>

                <div v-if="connectForm.platform_type === 'linkedin'" class="platform-credentials">
                    <div class="form-group">
                        <button type="button" @click="loginWithLinkedIn" class="btn-linkedin" :disabled="linkingLinkedIn">
                            <span v-if="!linkingLinkedIn">🔗 Login with LinkedIn</span>
                            <span v-else>Connecting...</span>
                        </button>
                        <small style="color: #666; font-size: 0.85rem; display: block; margin-top: 0.25rem;">
                            Click to connect your LinkedIn account automatically (Recommended)
                        </small>
                    </div>
                    
                    <div style="margin: 1rem 0; text-align: center; color: #666;">OR</div>
                    
                    <div class="form-group">
                        <label>Manual Connection - Access Token</label>
                        <input v-model="connectForm.credentials.access_token" type="text" placeholder="Enter access token manually" />
                        <small style="color: #666; font-size: 0.85rem; display: block; margin-top: 0.25rem;">Your LinkedIn OAuth access token (if you prefer manual entry)</small>
                    </div>
                    <div class="form-group">
                        <label>Person URN (Optional)</label>
                        <input v-model="connectForm.credentials.person_urn" type="text" placeholder="urn:li:person:xxxxx" />
                        <small style="color: #666; font-size: 0.85rem; display: block; margin-top: 0.25rem;">If not provided, will be fetched automatically from your profile</small>
                    </div>
                </div>

                <div v-if="connectForm.platform_type === 'youtube'" class="platform-credentials">
                    <div class="form-group">
                        <label>Credentials JSON</label>
                        <textarea v-model="connectForm.credentials.credentials" rows="6" required placeholder="Paste your Google OAuth credentials JSON"></textarea>
                    </div>
                </div>

                <div v-if="connectForm.platform_type === 'tiktok'" class="platform-credentials">
                    <div class="form-group">
                        <label>Access Token</label>
                        <input v-model="connectForm.credentials.access_token" type="text" required />
                    </div>
                    <div class="form-group">
                        <label>Advertiser ID</label>
                        <input v-model="connectForm.credentials.advertiser_id" type="text" required />
                    </div>
                </div>

                <div v-if="connectForm.platform_type === 'kaggle'" class="platform-credentials">
                    <div class="form-group">
                        <label>Kaggle Username (KAGGLE_USERNAME)</label>
                        <input v-model="connectForm.credentials.KAGGLE_USERNAME" type="text" required placeholder="your-kaggle-username" />
                        <small style="color: #666; font-size: 0.85rem; display: block; margin-top: 0.25rem;">Your Kaggle username</small>
                    </div>
                    <div class="form-group">
                        <label>Kaggle API Token (KAGGLE_API_TOKEN)</label>
                        <input v-model="connectForm.credentials.KAGGLE_API_TOKEN" type="password" required placeholder="Your Kaggle API token" />
                        <small style="color: #666; font-size: 0.85rem; display: block; margin-top: 0.25rem;">
                            Get this from <a href="https://www.kaggle.com/settings" target="_blank" style="color: #667eea;">Kaggle Settings</a> (Create New API Token)
                        </small>
                    </div>
                    <div style="padding: 1rem; background: #e3f2fd; border-radius: 4px; margin-top: 1rem; border-left: 4px solid #2196f3;">
                        <strong style="display: block; margin-bottom: 0.5rem; color: #1976d2;">📊 Dataset Upload Instructions:</strong>
                        <ul style="margin: 0.5rem 0 0 1.5rem; padding: 0; color: #555;">
                            <li>Enter your Kaggle username and API token above</li>
                            <li>When creating a post, upload a dataset file (CSV, JSON, ZIP, etc.)</li>
                            <li>The dataset will be uploaded to your Kaggle account</li>
                            <li>Make sure your API token has dataset upload permissions</li>
                        </ul>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="button" @click="testConnection" class="btn-secondary" :disabled="loading || !connectForm.platform_type || !hasCredentials">
                        {{ testingConnection ? 'Testing...' : 'Test Connection' }}
                    </button>
                    <button type="submit" class="btn-primary" :disabled="loading">
                        {{ loading ? 'Connecting...' : 'Connect Platform' }}
                    </button>
                </div>

                <div v-if="testResult" :class="testResult.success ? 'success-message' : 'error-message'">
                    {{ testResult.message }}
                </div>
                <div v-if="error" class="error-message">{{ error }}</div>
                <div v-if="success" class="success-message">{{ success }}</div>
            </form>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'Settings',
    setup() {
        const platforms = ref([]);
        const loading = ref(true);
        const connectForm = ref({
            platform_type: '',
            credentials: {},
        });
        const error = ref('');
        const success = ref('');
        const testingConnection = ref(false);
        const testResult = ref(null);
        const linkingLinkedIn = ref(false);

        const loadPlatforms = async () => {
            try {
                const response = await axios.get('/platforms');
                platforms.value = response.data;
            } catch (error) {
                console.error('Error loading platforms:', error);
            } finally {
                loading.value = false;
            }
        };

        const testConnection = async () => {
            if (!connectForm.value.platform_type || !hasCredentials.value) {
                testResult.value = {
                    success: false,
                    message: 'Please fill in all required credentials first',
                };
                return;
            }

            testingConnection.value = true;
            testResult.value = null;
            error.value = '';
            success.value = '';

            try {
                const response = await axios.post(`/platforms/${connectForm.value.platform_type}/test`, {
                    credentials: connectForm.value.credentials,
                });
                testResult.value = {
                    success: response.data.success,
                    message: response.data.message || 'Connection test completed',
                };
            } catch (err) {
                testResult.value = {
                    success: false,
                    message: err.response?.data?.message || 'Connection test failed',
                };
            } finally {
                testingConnection.value = false;
            }
        };

        const loginWithLinkedIn = async () => {
            linkingLinkedIn.value = true;
            error.value = '';
            success.value = '';

            try {
                const response = await axios.get('/platforms/linkedin/oauth/initiate');
                if (response.data.auth_url) {
                    // Redirect to LinkedIn OAuth
                    window.location.href = response.data.auth_url;
                } else {
                    error.value = 'Failed to initiate LinkedIn OAuth';
                    linkingLinkedIn.value = false;
                }
            } catch (err) {
                error.value = err.response?.data?.message || 'Failed to initiate LinkedIn login';
                linkingLinkedIn.value = false;
            }
        };

        const handleConnect = async () => {
            loading.value = true;
            error.value = '';
            success.value = '';
            testResult.value = null;

            try {
                await axios.post(`/platforms/${connectForm.value.platform_type}/connect`, {
                    credentials: connectForm.value.credentials,
                });
                success.value = 'Platform connected successfully!';
                connectForm.value = { platform_type: '', credentials: {} };
                await loadPlatforms();
            } catch (err) {
                error.value = err.response?.data?.message || 'Failed to connect platform';
            } finally {
                loading.value = false;
            }
        };

        const hasCredentials = computed(() => {
            if (!connectForm.value.platform_type) return false;
            const creds = connectForm.value.credentials;
            
            switch (connectForm.value.platform_type) {
                case 'facebook':
                    return !!(creds.page_id && creds.access_token);
                case 'linkedin':
                    return !!creds.access_token; // person_urn is optional, will be auto-fetched
                case 'youtube':
                    return !!creds.credentials;
                case 'tiktok':
                    return !!(creds.access_token && creds.advertiser_id);
                case 'kaggle':
                    return !!(creds.KAGGLE_USERNAME && creds.KAGGLE_API_TOKEN);
                default:
                    return false;
            }
        });

        const disconnectPlatform = async (platformType) => {
            if (!confirm(`Are you sure you want to disconnect ${platformType}?`)) return;

            try {
                await axios.delete(`/platforms/${platformType}/disconnect`);
                success.value = 'Platform disconnected successfully!';
                await loadPlatforms();
            } catch (err) {
                error.value = 'Failed to disconnect platform';
            }
        };

        onMounted(() => {
            loadPlatforms();
            
            // Check for OAuth callback parameters
            const urlParams = new URLSearchParams(window.location.search);
            const linkedinSuccess = urlParams.get('linkedin_success');
            const linkedinError = urlParams.get('linkedin_error');
            
            if (linkedinSuccess) {
                success.value = 'LinkedIn connected successfully!';
                loadPlatforms();
                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            if (linkedinError) {
                error.value = decodeURIComponent(linkedinError);
                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        return {
            platforms,
            loading,
            connectForm,
            error,
            success,
            testingConnection,
            testResult,
            linkingLinkedIn,
            hasCredentials,
            testConnection,
            loginWithLinkedIn,
            handleConnect,
            disconnectPlatform,
        };
    },
};
</script>

<style scoped>
.settings {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.settings h1 {
    margin-bottom: 2rem;
    color: #333;
}

.platforms-section,
.connect-section {
    margin-bottom: 3rem;
}

.platforms-section h2,
.connect-section h2 {
    margin-bottom: 1rem;
    color: #555;
}

.platforms-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.platform-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f9f9f9;
}

.platform-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.platform-info h3 {
    text-transform: capitalize;
    color: #333;
}

.platform-status {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.platform-status.active {
    background: #d4edda;
    color: #155724;
}

.connect-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
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

.form-group input,
.form-group select,
.form-group textarea {
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
    font-family: inherit;
}

.platform-credentials {
    padding: 1rem;
    background: #f9f9f9;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    gap: 1rem;
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

.btn-secondary {
    padding: 0.75rem 1.5rem;
    background-color: #6c757d;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-secondary:hover:not(:disabled) {
    background-color: #5a6268;
}

.btn-secondary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
}

.btn-linkedin {
    width: 100%;
    padding: 0.75rem 1.5rem;
    background-color: #0077b5;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-linkedin:hover:not(:disabled) {
    background-color: #005885;
}

.btn-linkedin:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.error-message {
    padding: 0.75rem;
    background-color: #fee;
    color: #c33;
    border-radius: 4px;
}

.success-message {
    padding: 0.75rem;
    background-color: #dfd;
    color: #3c3;
    border-radius: 4px;
}
</style>

