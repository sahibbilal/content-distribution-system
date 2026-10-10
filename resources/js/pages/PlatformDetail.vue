<template>
    <div class="mx-auto max-w-3xl">
        <button @click="goBack" class="btn btn-ghost -ml-3 mb-4 px-3! py-1.5!">
            <ArrowLeft class="h-4 w-4" /> Back to platforms
        </button>

        <div class="mb-8 flex items-center gap-4">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white shadow-card ring-1 ring-slate-900/5">
                <PlatformIcon :type="platformType" :size="32" />
            </span>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ platformInfo.name }}</h1>
                <p class="mt-0.5 text-sm text-slate-600">{{ platformInfo.description }}</p>
            </div>
        </div>

        <div v-if="loading" class="space-y-4">
            <div class="card h-28 animate-pulse bg-slate-100"></div>
            <div class="card h-64 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else class="space-y-6">
            <!-- Connection status -->
            <section class="card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="card-title">Connection status</h2>
                    <span class="badge" :class="isConnected ? 'badge-success' : 'badge-neutral'">
                        <span class="h-1.5 w-1.5 rounded-full" :class="isConnected ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                        {{ isConnected ? 'Connected' : 'Not connected' }}
                    </span>
                </div>
                <p v-if="isConnected" class="mt-2 text-sm text-slate-600">
                    Your {{ platformInfo.name }} account is connected and ready to use.
                </p>
                <p v-else class="mt-2 text-sm text-slate-600">
                    Connect your {{ platformInfo.name }} account to start publishing content.
                </p>
                <button v-if="isConnected" type="button" class="btn btn-secondary mt-4" @click="testSavedConnection" :disabled="testingConnection">
                    {{ testingConnection ? 'Testing...' : 'Test saved connection' }}
                </button>
            </section>

            <!-- LinkedIn -->
            <section v-if="platformType === 'linkedin'" class="card space-y-5 p-5">
                <div>
                    <h2 class="card-title">Connect with LinkedIn</h2>
                    <p class="mt-1 text-sm text-slate-500">Authorize and connect your LinkedIn account automatically.</p>
                </div>
                <button @click="loginWithLinkedIn" class="btn w-full bg-[#0a66c2] text-white hover:bg-[#084d92]" :disabled="linkingLinkedIn">
                    <PlatformIcon type="linkedin" :size="18" class="rounded-sm bg-white" />
                    <span v-if="!linkingLinkedIn">Log in with LinkedIn</span>
                    <span v-else>Connecting...</span>
                </button>

                <div class="flex items-center gap-3 text-xs font-medium uppercase tracking-wide text-slate-400">
                    <span class="h-px flex-1 bg-slate-200"></span> or connect manually <span class="h-px flex-1 bg-slate-200"></span>
                </div>

                <form @submit.prevent="handleManualConnect" class="space-y-5">
                    <div>
                        <label class="label">Access token</label>
                        <input v-model="credentials.access_token" type="text" class="input" placeholder="Enter your LinkedIn access token" />
                        <p class="hint">Your LinkedIn OAuth access token.</p>
                    </div>
                    <div>
                        <label class="label">Person URN (optional)</label>
                        <input v-model="credentials.person_urn" type="text" class="input" placeholder="urn:li:person:xxxxx" />
                        <p class="hint">If not provided, it is fetched automatically from your profile.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" @click="testConnection" class="btn btn-secondary" :disabled="testingConnection || !hasCredentials">
                            {{ testingConnection ? 'Testing...' : 'Test connection' }}
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="!hasCredentials || connecting">
                            {{ connecting ? 'Connecting...' : 'Save credentials' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- Facebook -->
            <section v-else-if="platformType === 'facebook'" class="card space-y-5 p-5">
                <div>
                    <h2 class="card-title">Connect with Facebook</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Authorize and connect your Facebook Pages and Instagram accounts. You'll be able to select which Page to connect.
                    </p>
                </div>
                <p v-if="!fbSDKReady" class="text-center text-sm text-slate-500">Loading Facebook login...</p>
                <!-- The Facebook SDK script rewrites this element's contents, so Vue must not manage any children -->
                <div class="flex min-h-[44px] items-center justify-center" id="fb-login-button-container"></div>

                <div class="flex items-center gap-3 text-xs font-medium uppercase tracking-wide text-slate-400">
                    <span class="h-px flex-1 bg-slate-200"></span> or connect manually <span class="h-px flex-1 bg-slate-200"></span>
                </div>

                <form @submit.prevent="handleManualConnect" class="space-y-5">
                    <div>
                        <label class="label">Page ID</label>
                        <input v-model="credentials.page_id" type="text" class="input" placeholder="Enter your Facebook Page ID" />
                        <p class="hint">Your Facebook Page ID.</p>
                    </div>
                    <div>
                        <label class="label">Access token</label>
                        <input v-model="credentials.access_token" type="text" class="input" placeholder="Enter your Page Access Token" />
                        <p class="hint">Page Access Token with the pages_manage_posts permission.</p>
                    </div>
                    <div>
                        <label class="label">Instagram account ID (optional)</label>
                        <input v-model="credentials.instagram_account_id" type="text" class="input" placeholder="Instagram Business Account ID" />
                        <p class="hint">For posting to Instagram (connected to your Facebook Page).</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" @click="testConnection" class="btn btn-secondary" :disabled="testingConnection || !hasCredentials">
                            {{ testingConnection ? 'Testing...' : 'Test connection' }}
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="!hasCredentials || connecting">
                            {{ connecting ? 'Connecting...' : 'Save credentials' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- YouTube -->
            <section v-else-if="platformType === 'youtube'" class="card p-5">
                <h2 class="card-title">Connect YouTube</h2>
                <form @submit.prevent="handleConnect" class="mt-5 space-y-5">
                    <div>
                        <label class="label">Credentials JSON</label>
                        <textarea v-model="credentials.credentials" rows="8" class="input font-mono text-xs" required placeholder="Paste your Google OAuth credentials JSON"></textarea>
                        <p class="hint">Your Google OAuth 2.0 credentials JSON.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" @click="testConnection" class="btn btn-secondary" :disabled="testingConnection || !hasCredentials">
                            {{ testingConnection ? 'Testing...' : 'Test connection' }}
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="!hasCredentials || connecting">
                            {{ connecting ? 'Connecting...' : 'Connect' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- TikTok -->
            <section v-else-if="platformType === 'tiktok'" class="card space-y-5 p-5">
                <div>
                    <h2 class="card-title">Connect with TikTok</h2>
                    <p class="mt-1 text-sm text-slate-500">Authorize and connect your TikTok account automatically.</p>
                </div>
                <button @click="loginWithTikTok" class="btn w-full bg-slate-900 text-white hover:bg-black" :disabled="linkingTikTok">
                    <PlatformIcon type="tiktok" :size="18" class="rounded-sm bg-white p-px" />
                    <span v-if="!linkingTikTok">Log in with TikTok</span>
                    <span v-else>Connecting...</span>
                </button>

                <div class="flex items-center gap-3 text-xs font-medium uppercase tracking-wide text-slate-400">
                    <span class="h-px flex-1 bg-slate-200"></span> or connect manually <span class="h-px flex-1 bg-slate-200"></span>
                </div>

                <form @submit.prevent="handleManualConnect" class="space-y-5">
                    <div>
                        <label class="label">Access token</label>
                        <input v-model="credentials.access_token" type="text" class="input" placeholder="Enter your TikTok access token" />
                        <p class="hint">Your TikTok OAuth access token.</p>
                    </div>
                    <div>
                        <label class="label">Open ID</label>
                        <input v-model="credentials.open_id" type="text" class="input" placeholder="Your TikTok Open ID" />
                        <p class="hint">Your TikTok Open ID (from the OAuth response).</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" @click="testConnection" class="btn btn-secondary" :disabled="testingConnection || !hasCredentials">
                            {{ testingConnection ? 'Testing...' : 'Test connection' }}
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="!hasCredentials || connecting">
                            {{ connecting ? 'Connecting...' : 'Save credentials' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- Test result -->
            <div v-if="testResult" ref="testResultEl" class="alert flex-col gap-0.5" :class="testResult.success ? 'alert-success' : 'alert-error'">
                <h4 class="flex items-center gap-2 font-semibold">
                    <CircleCheck v-if="testResult.success" class="h-4 w-4" />
                    <CircleX v-else class="h-4 w-4" />
                    {{ testResult.success ? 'Connection successful' : 'Connection failed' }}
                </h4>
                <p>{{ testResult.message }}</p>
            </div>

            <!-- Messages -->
            <div v-if="error" class="alert alert-error">{{ error }}</div>
            <div v-if="success" class="alert alert-success">{{ success }}</div>

            <!-- Disconnect -->
            <section v-if="isConnected" class="card flex flex-wrap items-center justify-between gap-4 p-5">
                <div>
                    <h2 class="card-title">Disconnect {{ platformInfo.name }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Publishing to this platform stops until you reconnect.</p>
                </div>
                <button @click="handleDisconnect" class="btn btn-danger">
                    Disconnect
                </button>
            </section>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import { ArrowLeft, CircleCheck, CircleX } from 'lucide-vue-next';
import PlatformIcon from '../components/PlatformIcon.vue';
import { confirmDialog } from '../feedback.js';

export default {
    name: 'PlatformDetail',
    components: { ArrowLeft, CircleCheck, CircleX, PlatformIcon },
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
        const testResultEl = ref(null);
        const credentials = ref({});
        const isConnected = ref(false);
        const platformId = ref(null);
        const fbSDKReady = ref(false);

        const platformInfo = computed(() => {
            const platforms = {
                linkedin: { name: 'LinkedIn', description: 'Professional networking platform' },
                facebook: { name: 'Facebook', description: 'Social media platform' },
                youtube: { name: 'YouTube', description: 'Video sharing platform' },
                tiktok: { name: 'TikTok', description: 'Short-form video platform' },
            };
            return platforms[platformType] || { name: platformType, description: '' };
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

        const testConnection = () => {
            if (!hasCredentials.value) {
                testResult.value = {
                    success: false,
                    message: 'Please fill in all required credentials first',
                };
                return;
            }

            return runTest({ credentials: credentials.value });
        };

        // Test the credentials already saved on the server (no re-entry needed)
        const testSavedConnection = () => runTest({});

        const runTest = async (payload) => {
            testingConnection.value = true;
            testResult.value = null;
            error.value = '';
            success.value = '';

            try {
                const response = await axios.post(`/platforms/${platformType}/test`, payload);
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
                await nextTick();
                testResultEl.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
            const confirmed = await confirmDialog({
                title: `Disconnect ${platformInfo.value.name}?`,
                message: 'Publishing to this platform stops until you reconnect it.',
                confirmText: 'Disconnect',
                danger: true,
            });
            if (!confirmed) return;

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
                // Function to render Facebook button
                const renderFacebookButton = () => {
                    if (typeof FB === 'undefined') {
                        return false;
                    }
                    
                    // Wait for DOM to be ready with multiple attempts
                    const tryRender = (attempts = 0) => {
                        const container = document.getElementById('fb-login-button-container');
                        
                        if (!container) {
                            if (attempts < 20) {
                                // Try again after a short delay (max 2 seconds)
                                setTimeout(() => tryRender(attempts + 1), 100);
                                return;
                            } else {
                                console.error('Facebook login button container not found after multiple attempts');
                                error.value = 'Failed to load Facebook login button. Please refresh the page.';
                                return;
                            }
                        }
                        
                        fbSDKReady.value = true;
                        
                        // Check if button already rendered
                        if (container.querySelector('iframe')) {
                            console.log('Facebook button already rendered');
                            return;
                        }
                        
                        // Create the XFBML button element
                        const buttonHTML = `
                            <fb:login-button 
                                onlogin="checkLoginState();"
                                data-size="large"
                                data-button-type="login_with"
                                data-layout="default"
                                data-auto-logout-link="false"
                                data-use-continue-as="false">
                            </fb:login-button>
                        `;
                        
                        container.innerHTML = buttonHTML;
                        
                        // Parse XFBML tags (Facebook login button)
                        try {
                            FB.XFBML.parse(container);
                            console.log('Facebook login button parsed successfully');
                            
                            // Double check after a short delay
                            setTimeout(() => {
                                if (!container.querySelector('iframe')) {
                                    console.warn('Facebook button not rendered, trying fallback');
                                    // Fallback: try parsing the entire document
                                    FB.XFBML.parse();
                                }
                            }, 1000);
                        } catch (e) {
                            console.error('Error parsing Facebook XFBML:', e);
                            error.value = 'Failed to load Facebook login button. Please refresh the page.';
                        }
                    };
                    
                    // Use nextTick first, then try rendering
                    nextTick(() => {
                        tryRender();
                    });
                    
                    return true;
                };
                
                // Wait a bit for Vue to render the component
                setTimeout(() => {
                    // Try to render immediately if SDK is ready
                    if (renderFacebookButton()) {
                        // Check current login status
                        checkLoginState();
                    } else {
                        // Wait for SDK to load
                        const checkFB = setInterval(() => {
                            if (typeof FB !== 'undefined' && renderFacebookButton()) {
                                clearInterval(checkFB);
                                // Check current login status
                                checkLoginState();
                            }
                        }, 100);
                        
                        // Clear interval after 10 seconds
                        setTimeout(() => {
                            clearInterval(checkFB);
                            if (!fbSDKReady.value) {
                                error.value = 'Facebook SDK failed to load. Please refresh the page.';
                            }
                        }, 10000);
                    }
                }, 200); // Small delay to ensure Vue has rendered
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
            error,
            success,
            testResult,
            testResultEl,
            credentials,
            isConnected,
            hasCredentials,
            fbSDKReady,
            loginWithLinkedIn,
            loginWithTikTok,
            testConnection,
            testSavedConnection,
            handleConnect,
            handleManualConnect,
            handleDisconnect,
            goBack,
        };
    },
};
</script>
