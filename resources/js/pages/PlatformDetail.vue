<template>
    <div class="platform-detail">
        <div class="platform-header">
            <button @click="goBack" class="btn-back">← Back to Platforms</button>
            <div class="platform-title">
                <span class="platform-icon">{{ platformInfo.icon }}</span>
                <h1>{{ platformInfo.name }}</h1>
            </div>
            <p class="platform-description">{{ platformInfo.description }}</p>
        </div>

        <div v-if="loading" class="loading">Loading...</div>

        <div v-else class="platform-content">
            <!-- Connection Status -->
            <div class="status-card" :class="{ connected: isConnected, disconnected: !isConnected }">
                <div class="status-header">
                    <h3>Connection Status</h3>
                    <span class="status-badge" :class="{ connected: isConnected, disconnected: !isConnected }">
                        {{ isConnected ? '✓ Connected' : 'Not Connected' }}
                    </span>
                </div>
                <p v-if="isConnected" class="status-message">
                    Your {{ platformInfo.name }} account is connected and ready to use.
                </p>
                <p v-else class="status-message">
                    Connect your {{ platformInfo.name }} account to start publishing content.
                </p>
            </div>

            <!-- LinkedIn OAuth Login -->
            <div v-if="platformType === 'linkedin'" class="connection-section">
                <h3>Connect with LinkedIn</h3>
                <button 
                    @click="loginWithLinkedIn" 
                    class="btn-linkedin" 
                    :disabled="linkingLinkedIn"
                >
                    <span v-if="!linkingLinkedIn">🔗 Login with LinkedIn</span>
                    <span v-else>Connecting...</span>
                </button>
                <p class="help-text">
                    Click to authorize and connect your LinkedIn account automatically.
                </p>

                <div class="divider">OR</div>

                <h3>Manual Connection</h3>
                <form @submit.prevent="handleManualConnect" class="credentials-form">
                    <div class="form-group">
                        <label>Access Token</label>
                        <input 
                            v-model="credentials.access_token" 
                            type="text" 
                            placeholder="Enter your LinkedIn access token"
                        />
                        <small>Your LinkedIn OAuth access token</small>
                    </div>
                    <div class="form-group">
                        <label>Person URN (Optional)</label>
                        <input 
                            v-model="credentials.person_urn" 
                            type="text" 
                            placeholder="urn:li:person:xxxxx"
                        />
                        <small>If not provided, will be fetched automatically from your profile</small>
                    </div>
                    <div class="form-actions">
                        <button 
                            type="button" 
                            @click="testConnection" 
                            class="btn-secondary"
                            :disabled="testingConnection || !hasCredentials"
                        >
                            {{ testingConnection ? 'Testing...' : 'Test Connection' }}
                        </button>
                        <button 
                            type="submit" 
                            class="btn-primary"
                            :disabled="!hasCredentials || connecting"
                        >
                            {{ connecting ? 'Connecting...' : 'Save Credentials' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Facebook OAuth Login -->
            <div v-else-if="platformType === 'facebook'" class="connection-section">
                <h3>Connect with Facebook</h3>
                <div class="facebook-login-wrapper" id="fb-login-button-container"></div>
                <p class="help-text">
                    Click to authorize and connect your Facebook Pages and Instagram accounts automatically. 
                    You'll be able to select which Page to connect.
                </p>

                <div class="divider">OR</div>

                <h3>Manual Connection</h3>
                <form @submit.prevent="handleManualConnect" class="credentials-form">
                    <div class="form-group">
                        <label>Page ID</label>
                        <input 
                            v-model="credentials.page_id" 
                            type="text" 
                            placeholder="Enter your Facebook Page ID"
                        />
                        <small>Your Facebook Page ID</small>
                    </div>
                    <div class="form-group">
                        <label>Access Token</label>
                        <input 
                            v-model="credentials.access_token" 
                            type="text" 
                            placeholder="Enter your Page Access Token"
                        />
                        <small>Page Access Token with pages_manage_posts permission</small>
                    </div>
                    <div class="form-group">
                        <label>Instagram Account ID (Optional)</label>
                        <input 
                            v-model="credentials.instagram_account_id" 
                            type="text" 
                            placeholder="Instagram Business Account ID"
                        />
                        <small>For posting to Instagram (connected to your Facebook Page)</small>
                    </div>
                    <div class="form-actions">
                        <button 
                            type="button" 
                            @click="testConnection" 
                            class="btn-secondary"
                            :disabled="testingConnection || !hasCredentials"
                        >
                            {{ testingConnection ? 'Testing...' : 'Test Connection' }}
                        </button>
                        <button 
                            type="submit" 
                            class="btn-primary"
                            :disabled="!hasCredentials || connecting"
                        >
                            {{ connecting ? 'Connecting...' : 'Save Credentials' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- YouTube Connection -->
            <div v-else-if="platformType === 'youtube'" class="connection-section">
                <h3>Connect YouTube</h3>
                <form @submit.prevent="handleConnect" class="credentials-form">
                    <div class="form-group">
                        <label>Credentials JSON</label>
                        <textarea 
                            v-model="credentials.credentials" 
                            rows="8" 
                            required 
                            placeholder="Paste your Google OAuth credentials JSON"
                        ></textarea>
                        <small>Your Google OAuth 2.0 credentials JSON</small>
                    </div>
                    <div class="form-actions">
                        <button 
                            type="button" 
                            @click="testConnection" 
                            class="btn-secondary"
                            :disabled="testingConnection || !hasCredentials"
                        >
                            {{ testingConnection ? 'Testing...' : 'Test Connection' }}
                        </button>
                        <button 
                            type="submit" 
                            class="btn-primary"
                            :disabled="!hasCredentials || connecting"
                        >
                            {{ connecting ? 'Connecting...' : 'Connect' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- TikTok OAuth Login -->
            <div v-else-if="platformType === 'tiktok'" class="connection-section">
                <h3>Connect with TikTok</h3>
                <button 
                    @click="loginWithTikTok" 
                    class="btn-tiktok" 
                    :disabled="linkingTikTok"
                >
                    <span v-if="!linkingTikTok">🎵 Login with TikTok</span>
                    <span v-else>Connecting...</span>
                </button>
                <p class="help-text">
                    Click to authorize and connect your TikTok account automatically.
                </p>

                <div class="divider">OR</div>

                <h3>Manual Connection</h3>
                <form @submit.prevent="handleManualConnect" class="credentials-form">
                    <div class="form-group">
                        <label>Access Token</label>
                        <input 
                            v-model="credentials.access_token" 
                            type="text" 
                            placeholder="Enter your TikTok access token"
                        />
                        <small>Your TikTok OAuth access token</small>
                    </div>
                    <div class="form-group">
                        <label>Open ID</label>
                        <input 
                            v-model="credentials.open_id" 
                            type="text" 
                            placeholder="Your TikTok Open ID"
                        />
                        <small>Your TikTok Open ID (from OAuth response)</small>
                    </div>
                    <div class="form-actions">
                        <button 
                            type="button" 
                            @click="testConnection" 
                            class="btn-secondary"
                            :disabled="testingConnection || !hasCredentials"
                        >
                            {{ testingConnection ? 'Testing...' : 'Test Connection' }}
                        </button>
                        <button 
                            type="submit" 
                            class="btn-primary"
                            :disabled="!hasCredentials || connecting"
                        >
                            {{ connecting ? 'Connecting...' : 'Save Credentials' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Kaggle Connection -->
            <div v-else-if="platformType === 'kaggle'" class="connection-section">
                <h3>Connect Kaggle</h3>
                <form @submit.prevent="handleConnect" class="credentials-form">
                    <div class="form-group">
                        <label>Kaggle Username</label>
                        <input 
                            v-model="credentials.KAGGLE_USERNAME" 
                            type="text" 
                            required 
                            placeholder="your-kaggle-username"
                        />
                        <small>Your Kaggle username</small>
                    </div>
                    <div class="form-group">
                        <label>Kaggle API Token</label>
                        <input 
                            v-model="credentials.KAGGLE_API_TOKEN" 
                            type="text" 
                            required 
                            placeholder="KGAT_xxxxxxxxxxxxx"
                        />
                        <small>
                            Create an API token at 
                            <a href="https://www.kaggle.com/settings/account" target="_blank">Kaggle Settings</a>
                        </small>
                    </div>
                    <div class="form-actions">
                        <button 
                            type="button" 
                            @click="testConnection" 
                            class="btn-secondary"
                            :disabled="testingConnection || !hasCredentials"
                        >
                            {{ testingConnection ? 'Testing...' : 'Test Connection' }}
                        </button>
                        <button 
                            type="submit" 
                            class="btn-primary"
                            :disabled="!hasCredentials || connecting"
                        >
                            {{ connecting ? 'Connecting...' : 'Connect' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Test Result -->
            <div v-if="testResult" class="test-result" :class="{ success: testResult.success, error: !testResult.success }">
                <h4>{{ testResult.success ? '✓ Connection Successful' : '✗ Connection Failed' }}</h4>
                <p>{{ testResult.message }}</p>
            </div>

            <!-- Error/Success Messages -->
            <div v-if="error" class="error-message">{{ error }}</div>
            <div v-if="success" class="success-message">{{ success }}</div>

            <!-- Disconnect Button -->
            <div v-if="isConnected" class="disconnect-section">
                <button @click="handleDisconnect" class="btn-danger">
                    Disconnect {{ platformInfo.name }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';

export default {
    name: 'PlatformDetail',
    setup() {
        const router = useRouter();
        const route = useRoute();
        const platformType = route.params.platform;
        const loading = ref(true);
        const connecting = ref(false);
        const testingConnection = ref(false);
        const linkingLinkedIn = ref(false);
        const linkingTikTok = ref(false);
        const linkingFacebook = ref(false);
        const error = ref('');
        const success = ref('');
        const testResult = ref(null);
        const credentials = ref({});
        const isConnected = ref(false);
        const platformId = ref(null);

        const platformInfo = computed(() => {
            const platforms = {
                linkedin: { name: 'LinkedIn', icon: '💼', description: 'Professional networking platform' },
                facebook: { name: 'Facebook', icon: '📘', description: 'Social media platform' },
                youtube: { name: 'YouTube', icon: '📺', description: 'Video sharing platform' },
                tiktok: { name: 'TikTok', icon: '🎵', description: 'Short-form video platform' },
                kaggle: { name: 'Kaggle', icon: '📊', description: 'Data science platform' },
            };
            return platforms[platformType] || { name: platformType, icon: '🔗', description: '' };
        });

        const hasCredentials = computed(() => {
            if (!platformType) return false;
            const creds = credentials.value;
            
            switch (platformType) {
                case 'facebook':
                    return !!(creds.access_token && (creds.page_id || creds.instagram_account_id));
                case 'linkedin':
                    return !!creds.access_token;
                case 'youtube':
                    return !!creds.credentials;
                case 'tiktok':
                    return !!(creds.access_token && creds.open_id);
                case 'kaggle':
                    return !!(creds.KAGGLE_USERNAME && creds.KAGGLE_API_TOKEN);
                default:
                    return false;
            }
        });

        const loadPlatform = async () => {
            try {
                const response = await axios.get('/platforms');
                const platform = response.data.find(p => p.platform_type === platformType);
                if (platform) {
                    isConnected.value = platform.is_active;
                    platformId.value = platform.id;
                }
            } catch (error) {
                console.error('Error loading platform:', error);
            } finally {
                loading.value = false;
            }
        };

        const loginWithLinkedIn = async () => {
            if (platformType !== 'linkedin') return;
            
            linkingLinkedIn.value = true;
            error.value = '';
            success.value = '';

            try {
                const response = await axios.get('/platforms/linkedin/oauth/initiate');
                if (response.data.auth_url) {
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

        const loginWithTikTok = async () => {
            if (platformType !== 'tiktok') return;
            
            linkingTikTok.value = true;
            error.value = '';
            success.value = '';

            try {
                const response = await axios.get('/platforms/tiktok/oauth/initiate');
                if (response.data.auth_url) {
                    window.location.href = response.data.auth_url;
                } else {
                    error.value = 'Failed to initiate TikTok OAuth';
                    linkingTikTok.value = false;
                }
            } catch (err) {
                error.value = err.response?.data?.message || 'Failed to initiate TikTok login';
                linkingTikTok.value = false;
            }
        };

        // Global function for Facebook login button callback
        const checkLoginState = () => {
            if (typeof FB !== 'undefined') {
                FB.getLoginStatus(function(response) {
                    statusChangeCallback(response);
                });
            }
        };

        // Global callback function for Facebook login status
        const statusChangeCallback = async (response) => {
            console.log('Facebook login status changed:', response);
            
            if (response.status === 'connected') {
                // User is logged into Facebook
                linkingFacebook.value = true;
                error.value = '';
                success.value = '';
                
                try {
                    // Get user's pages
                    const authResponse = response.authResponse;
                    
                    // First, verify the token with our backend and get pages
                    const pagesResponse = await axios.get('/platforms/facebook/oauth/pages', {
                        params: {
                            access_token: authResponse.accessToken
                        }
                    });
                    
                    if (pagesResponse.data.pages && pagesResponse.data.pages.length > 0) {
                        // If multiple pages, let user select (for now, use first page)
                        const selectedPage = pagesResponse.data.pages[0];
                        
                        // Connect the platform with the page credentials
                        await axios.post('/platforms/facebook/connect', {
                            credentials: {
                                access_token: selectedPage.access_token,
                                page_id: selectedPage.id,
                                page_name: selectedPage.name,
                                instagram_account_id: selectedPage.instagram_business_account?.id || null,
                            }
                        });
                        
                        success.value = `Successfully connected to ${selectedPage.name}!`;
                        await loadPlatform();
                    } else {
                        error.value = 'No Facebook Pages found. Please create a Facebook Page first.';
                    }
                } catch (err) {
                    error.value = err.response?.data?.message || 'Failed to connect Facebook account';
                    console.error('Facebook connection error:', err);
                } finally {
                    linkingFacebook.value = false;
                }
            } else if (response.status === 'not_authorized') {
                // User is logged into Facebook but not authorized for this app
                error.value = 'Please authorize this app to access your Facebook account.';
                linkingFacebook.value = false;
            } else {
                // User is not logged into Facebook
                linkingFacebook.value = false;
            }
        };

        // Make functions available globally for Facebook SDK callbacks
        if (typeof window !== 'undefined') {
            window.checkLoginState = checkLoginState;
            window.statusChangeCallback = statusChangeCallback;
        }

        const testConnection = async () => {
            if (!hasCredentials.value) {
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
                const response = await axios.post(`/platforms/${platformType}/test`, {
                    credentials: credentials.value,
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

        const handleConnect = async () => {
            connecting.value = true;
            error.value = '';
            success.value = '';
            testResult.value = null;

            try {
                await axios.post(`/platforms/${platformType}/connect`, {
                    credentials: credentials.value,
                });
                success.value = 'Platform connected successfully!';
                credentials.value = {};
                await loadPlatform();
            } catch (err) {
                error.value = err.response?.data?.message || 'Failed to connect platform';
            } finally {
                connecting.value = false;
            }
        };

        const handleManualConnect = async () => {
            await handleConnect();
        };

        const handleDisconnect = async () => {
            if (!confirm(`Are you sure you want to disconnect ${platformInfo.value.name}?`)) return;

            try {
                await axios.delete(`/platforms/${platformType}/disconnect`);
                success.value = 'Platform disconnected successfully!';
                isConnected.value = false;
                platformId.value = null;
            } catch (err) {
                error.value = 'Failed to disconnect platform';
            }
        };

        const goBack = () => {
            router.push('/platforms');
        };

        onMounted(() => {
            loadPlatform();
            
            // Check for OAuth callback parameters (for LinkedIn and TikTok)
            const urlParams = new URLSearchParams(window.location.search);
            const linkedinSuccess = urlParams.get('linkedin_success');
            const linkedinError = urlParams.get('linkedin_error');
            const tiktokSuccess = urlParams.get('tiktok_success');
            const tiktokError = urlParams.get('tiktok_error');
            const facebookSuccess = urlParams.get('facebook_success');
            const facebookError = urlParams.get('facebook_error');
            const facebookMessage = urlParams.get('message');
            
            if (linkedinSuccess) {
                success.value = 'LinkedIn connected successfully!';
                loadPlatform();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            if (linkedinError) {
                error.value = decodeURIComponent(linkedinError);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            if (tiktokSuccess) {
                success.value = 'TikTok connected successfully!';
                loadPlatform();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            if (tiktokError) {
                error.value = decodeURIComponent(tiktokError);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            // Handle Facebook OAuth callback (fallback for server-side OAuth)
            if (facebookSuccess) {
                success.value = facebookMessage ? decodeURIComponent(facebookMessage) : 'Facebook connected successfully!';
                loadPlatform();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            if (facebookError) {
                error.value = decodeURIComponent(facebookError);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            // Check Facebook login status if on Facebook platform page
            if (platformType === 'facebook' && typeof window !== 'undefined') {
                // Wait for Facebook SDK to be ready and render login button
                const checkFB = setInterval(() => {
                    if (typeof FB !== 'undefined') {
                        clearInterval(checkFB);
                        
                        // Use nextTick to ensure DOM is ready
                        nextTick(() => {
                            const container = document.getElementById('fb-login-button-container');
                            if (container && !container.querySelector('iframe')) {
                                // Create the XFBML button element
                                container.innerHTML = `
                                    <fb:login-button 
                                        scope="pages_manage_posts,pages_show_list,pages_read_engagement,instagram_basic,instagram_content_publish,business_management"
                                        onlogin="checkLoginState();"
                                        data-size="large"
                                        data-button-type="login_with"
                                        data-layout="default"
                                        data-auto-logout-link="false"
                                        data-use-continue-as="false">
                                    </fb:login-button>
                                `;
                                // Parse XFBML tags (Facebook login button)
                                FB.XFBML.parse(container);
                            }
                        });
                        
                        // Check current login status
                        checkLoginState();
                    }
                }, 100);
                
                // Clear interval after 5 seconds
                setTimeout(() => clearInterval(checkFB), 5000);
            }
        });

        return {
            platformType,
            platformInfo,
            loading,
            connecting,
            testingConnection,
            linkingLinkedIn,
            linkingTikTok,
            linkingFacebook,
            error,
            success,
            testResult,
            credentials,
            isConnected,
            hasCredentials,
            loginWithLinkedIn,
            loginWithTikTok,
            testConnection,
            handleConnect,
            handleManualConnect,
            handleDisconnect,
            goBack,
        };
    },
};
</script>

<style scoped>
.platform-detail {
    padding: 2rem;
    max-width: 900px;
    margin: 0 auto;
}

.btn-back {
    background: none;
    border: none;
    color: #667eea;
    cursor: pointer;
    font-size: 0.9rem;
    margin-bottom: 1rem;
    padding: 0.5rem 0;
}

.btn-back:hover {
    text-decoration: underline;
}

.platform-header {
    margin-bottom: 2rem;
}

.platform-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.platform-icon {
    font-size: 3rem;
}

.platform-title h1 {
    margin: 0;
    color: #333;
}

.platform-description {
    color: #666;
    margin: 0;
}

.status-card {
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    border: 2px solid;
}

.status-card.connected {
    background: #d4edda;
    border-color: #c3e6cb;
}

.status-card.disconnected {
    background: #f8d7da;
    border-color: #f5c6cb;
}

.status-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.status-header h3 {
    margin: 0;
    color: #333;
}

.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.status-badge.connected {
    background: #155724;
    color: white;
}

.status-badge.disconnected {
    background: #721c24;
    color: white;
}

.status-message {
    margin: 0;
    color: #555;
}

.connection-section {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.connection-section h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #333;
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
    margin-bottom: 1rem;
}

.btn-linkedin:hover:not(:disabled) {
    background-color: #005885;
}

.btn-linkedin:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-tiktok {
    width: 100%;
    padding: 0.75rem 1.5rem;
    background-color: #000000;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
    margin-bottom: 1rem;
}

.btn-tiktok:hover:not(:disabled) {
    background-color: #333333;
}

.btn-tiktok:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.facebook-login-wrapper {
    margin-bottom: 1rem;
    display: flex;
    justify-content: center;
}

.facebook-login-wrapper iframe {
    max-width: 100%;
}

.help-text {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 1.5rem;
}

.divider {
    text-align: center;
    margin: 2rem 0;
    color: #999;
    font-weight: 500;
    position: relative;
}

.divider::before,
.divider::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 45%;
    height: 1px;
    background: #ddd;
}

.divider::before {
    left: 0;
}

.divider::after {
    right: 0;
}

.credentials-form {
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

.form-group input,
.form-group textarea {
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
    font-family: inherit;
}

.form-group textarea {
    resize: vertical;
    font-family: monospace;
}

.form-group small {
    color: #666;
    font-size: 0.85rem;
}

.form-group small a {
    color: #667eea;
    text-decoration: none;
}

.form-group small a:hover {
    text-decoration: underline;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.btn-primary,
.btn-secondary,
.btn-danger {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    cursor: pointer;
    transition: background-color 0.2s;
    flex: 1;
}

.btn-primary {
    background-color: #667eea;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background-color: #5568d3;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover:not(:disabled) {
    background-color: #5a6268;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-danger:hover {
    background-color: #c82333;
}

.btn-primary:disabled,
.btn-secondary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.test-result {
    padding: 1rem;
    border-radius: 4px;
    margin: 1rem 0;
}

.test-result.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.test-result.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.test-result h4 {
    margin: 0 0 0.5rem 0;
}

.test-result p {
    margin: 0;
}

.error-message {
    padding: 0.75rem;
    background-color: #fee;
    color: #c33;
    border-radius: 4px;
    margin: 1rem 0;
}

.success-message {
    padding: 0.75rem;
    background-color: #dfd;
    color: #3c3;
    border-radius: 4px;
    margin: 1rem 0;
}

.disconnect-section {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid #ddd;
}

.loading {
    text-align: center;
    padding: 3rem;
    color: #666;
}
</style>
