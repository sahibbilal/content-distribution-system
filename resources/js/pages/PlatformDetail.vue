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
                        Authorize and connect your Facebook Page to publish posts to it.
                    </p>
                </div>
                <button @click="loginWithFacebook" class="btn w-full bg-slate-900 text-white hover:bg-black" :disabled="linkingFacebook">
                    <PlatformIcon type="facebook" :size="18" class="rounded-sm bg-white p-px" />
                    <span v-if="!linkingFacebook">Log in with Facebook</span>
                    <span v-else>Connecting...</span>
                </button>
            </section>

            <!-- YouTube -->
            <section v-else-if="platformType === 'youtube'" class="card space-y-5 p-5">
                <div>
                    <h2 class="card-title">Connect with YouTube</h2>
                    <p class="mt-1 text-sm text-slate-500">Sign in with Google to upload videos to your channel. A video is required for every YouTube post; the first line of your text becomes the title.</p>
                </div>
                <button @click="loginWithYouTube" class="btn w-full bg-slate-900 text-white hover:bg-black" :disabled="linkingYouTube">
                    <PlatformIcon type="youtube" :size="18" />
                    <span v-if="!linkingYouTube">Sign in with Google</span>
                    <span v-else>Connecting...</span>
                </button>
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

            <!-- X (Twitter) -->
            <section v-else-if="platformType === 'twitter'" class="card space-y-5 p-5">
                <div>
                    <h2 class="card-title">Connect with X</h2>
                    <p class="mt-1 text-sm text-slate-500">Authorize your X account to publish tweets (up to 280 characters, with up to 4 images).</p>
                </div>
                <button @click="loginWithTwitter" class="btn w-full bg-slate-900 text-white hover:bg-black" :disabled="linkingTwitter">
                    <PlatformIcon type="twitter" :size="18" class="rounded-sm bg-white p-px" />
                    <span v-if="!linkingTwitter">Log in with X</span>
                    <span v-else>Connecting...</span>
                </button>
            </section>

            <!-- Instagram -->
            <section v-else-if="platformType === 'instagram'" class="card space-y-5 p-5">
                <div>
                    <h2 class="card-title">Connect with Instagram</h2>
                    <p class="mt-1 text-sm text-slate-500">Authorize a Business or Creator account to publish photos and Reels. An image or video is required for every post.</p>
                </div>
                <button @click="loginWithInstagram" class="btn w-full bg-slate-900 text-white hover:bg-black" :disabled="linkingInstagram">
                    <PlatformIcon type="instagram" :size="18" />
                    <span v-if="!linkingInstagram">Log in with Instagram</span>
                    <span v-else>Connecting...</span>
                </button>
            </section>

            <!-- Website -->
            <section v-else-if="platformType === 'website'" class="card space-y-5 p-5">
                <div>
                    <h2 class="card-title">Connect your website</h2>
                    <p class="mt-1 text-sm text-slate-500">Posts are published immediately through your site's API. The first line of your text becomes the post title.</p>
                </div>
                <WebsiteApiGuide />
                <form @submit.prevent="handleManualConnect" class="space-y-5">
                    <div>
                        <label class="label">API URL</label>
                        <input v-model="credentials.api_url" type="url" class="input" placeholder="https://bilalmahmood.dev/api" />
                        <p class="hint">The base URL of your API, without a trailing /posts.</p>
                    </div>
                    <div>
                        <label class="label">Bearer token</label>
                        <input v-model="credentials.api_token" type="password" autocomplete="off" class="input" placeholder="Enter your API token" />
                        <p class="hint">Sent as <code>Authorization: Bearer ...</code>. Saving again replaces the stored token.</p>
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
import WebsiteApiGuide from '../components/WebsiteApiGuide.vue';
import { confirmDialog } from '../feedback.js';

export default {
    name: 'PlatformDetail',
    components: { ArrowLeft, CircleCheck, CircleX, PlatformIcon, WebsiteApiGuide },
    setup() {
        const router = useRouter();
        const route = useRoute();
        const platformType = route.params.platform;
        const loading = ref(true);
        const connecting = ref(false);
        const testingConnection = ref(false);
        const linkingLinkedIn = ref(false);
        const linkingTikTok = ref(false);
        const linkingTwitter = ref(false);
        const linkingInstagram = ref(false);
        const linkingYouTube = ref(false);
        const linkingFacebook = ref(false);
        const error = ref('');
        const success = ref('');
        const testResult = ref(null);
        const testResultEl = ref(null);
        const credentials = ref({});
        const isConnected = ref(false);
        const platformId = ref(null);

        const platformInfo = computed(() => {
            const platforms = {
                linkedin: { name: 'LinkedIn', description: 'Professional networking platform' },
                facebook: { name: 'Facebook', description: 'Social media platform' },
                youtube: { name: 'YouTube', description: 'Video sharing platform' },
                tiktok: { name: 'TikTok', description: 'Short-form video platform' },
                twitter: { name: 'X (Twitter)', description: 'Short text posts (tweets)' },
                instagram: { name: 'Instagram', description: 'Photos and Reels' },
                website: { name: 'Website', description: 'Blog posts on your own site through its API' },
            };
            return platforms[platformType] || { name: platformType, description: '' };
        });

        const hasCredentials = computed(() => {
            if (!platformType) return false;
            const creds = credentials.value;
            
            switch (platformType) {
                case 'linkedin':
                    return !!creds.access_token;
                case 'tiktok':
                    return !!(creds.access_token && creds.open_id);
                case 'website':
                    return !!(creds.api_url && creds.api_token);
                default:
                    return false;
            }
        });

        const loadPlatform = async () => {
            try {
                const response = await axios.get('/platforms');
                const platform = response.data.find(p => p.platform_type === platformType);
                if (platform) {
                    isConnected.value = platform.is_active && platform.credentials_valid;
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

        const loginWithTwitter = async () => {
            if (platformType !== 'twitter') return;

            linkingTwitter.value = true;
            error.value = '';
            success.value = '';

            try {
                const response = await axios.get('/platforms/twitter/oauth/initiate');
                if (response.data.auth_url) {
                    window.location.href = response.data.auth_url;
                } else {
                    error.value = 'Failed to initiate X OAuth';
                    linkingTwitter.value = false;
                }
            } catch (err) {
                error.value = err.response?.data?.message || 'Failed to initiate X login';
                linkingTwitter.value = false;
            }
        };

        const loginWithInstagram = async () => {
            if (platformType !== 'instagram') return;

            linkingInstagram.value = true;
            error.value = '';
            success.value = '';

            try {
                const response = await axios.get('/platforms/instagram/oauth/initiate');
                if (response.data.auth_url) {
                    window.location.href = response.data.auth_url;
                } else {
                    error.value = 'Failed to initiate Instagram OAuth';
                    linkingInstagram.value = false;
                }
            } catch (err) {
                error.value = err.response?.data?.message || 'Failed to initiate Instagram login';
                linkingInstagram.value = false;
            }
        };

        const loginWithYouTube = async () => {
            if (platformType !== 'youtube') return;

            linkingYouTube.value = true;
            error.value = '';
            success.value = '';

            try {
                const response = await axios.get('/platforms/youtube/oauth/initiate');
                if (response.data.auth_url) {
                    window.location.href = response.data.auth_url;
                } else {
                    error.value = 'Failed to initiate YouTube OAuth';
                    linkingYouTube.value = false;
                }
            } catch (err) {
                error.value = err.response?.data?.message || 'Failed to initiate YouTube login';
                linkingYouTube.value = false;
            }
        };

        const loginWithFacebook = async () => {
            if (platformType !== 'facebook') return;

            linkingFacebook.value = true;
            error.value = '';
            success.value = '';

            try {
                const response = await axios.get('/platforms/facebook/oauth/initiate');
                if (response.data.auth_url) {
                    window.location.href = response.data.auth_url;
                } else {
                    error.value = 'Failed to initiate Facebook OAuth';
                    linkingFacebook.value = false;
                }
            } catch (err) {
                error.value = err.response?.data?.message || 'Failed to initiate Facebook login';
                linkingFacebook.value = false;
            }
        };

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
                    // The website API is the user's own, so check the URL and token before saving anything
                    test_first: platformType === 'website',
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
            const twitterSuccess = urlParams.get('twitter_success');
            const twitterError = urlParams.get('twitter_error');
            const instagramSuccess = urlParams.get('instagram_success');
            const instagramError = urlParams.get('instagram_error');
            const youtubeSuccess = urlParams.get('youtube_success');
            const youtubeError = urlParams.get('youtube_error');
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
            
            if (twitterSuccess) {
                success.value = 'X connected successfully!';
                loadPlatform();
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            if (twitterError) {
                error.value = decodeURIComponent(twitterError);
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            if (instagramSuccess) {
                success.value = 'Instagram connected successfully!';
                loadPlatform();
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            if (instagramError) {
                error.value = decodeURIComponent(instagramError);
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            if (youtubeSuccess) {
                success.value = 'YouTube connected successfully!';
                loadPlatform();
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            if (youtubeError) {
                error.value = decodeURIComponent(youtubeError);
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            // Handle Facebook OAuth callback
            if (facebookSuccess) {
                success.value = facebookMessage ? decodeURIComponent(facebookMessage) : 'Facebook connected successfully!';
                loadPlatform();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            if (facebookError) {
                error.value = decodeURIComponent(facebookError);
                window.history.replaceState({}, document.title, window.location.pathname);
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
            linkingTwitter,
            linkingInstagram,
            linkingYouTube,
            linkingFacebook,
            error,
            success,
            testResult,
            testResultEl,
            credentials,
            isConnected,
            hasCredentials,
            loginWithLinkedIn,
            loginWithTikTok,
            loginWithTwitter,
            loginWithInstagram,
            loginWithFacebook,
            loginWithYouTube,
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
