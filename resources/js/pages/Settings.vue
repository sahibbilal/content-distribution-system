<template>
    <div>
        <PageHeader title="Platform settings" description="Review connected accounts and add new connections." />

        <div class="grid gap-6 xl:grid-cols-2">
            <!-- Connected platforms -->
            <section class="card self-start">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="card-title">Connected platforms</h2>
                </div>
                <div v-if="loading" class="space-y-3 p-5">
                    <div v-for="n in 2" :key="n" class="h-14 animate-pulse rounded-lg bg-slate-100"></div>
                </div>
                <div v-else-if="platforms.length === 0" class="px-5 py-12 text-center">
                    <Plug class="mx-auto h-8 w-8 text-slate-300" />
                    <p class="mt-3 text-sm text-slate-500">No platforms connected yet</p>
                </div>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="platform in platforms" :key="platform.id" class="flex items-center gap-4 p-5">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-50 ring-1 ring-slate-900/5">
                            <PlatformIcon :type="platform.platform_type" :size="24" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold text-slate-900">{{ platformLabel(platform.platform_type) }}</h3>
                            <span class="badge mt-1" :class="platform.is_active ? 'badge-success' : 'badge-neutral'">
                                {{ platform.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <button @click="disconnectPlatform(platform.platform_type)" class="btn btn-danger-soft btn-sm">
                            Disconnect
                        </button>
                    </li>
                </ul>
            </section>

            <!-- Connect platform -->
            <section class="card">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="card-title">Connect a platform</h2>
                </div>
                <form @submit.prevent="handleConnect" class="space-y-5 p-5">
                    <div>
                        <label class="label">Platform</label>
                        <select v-model="connectForm.platform_type" class="input" required>
                            <option value="">Select a platform</option>
                            <option value="facebook">Facebook</option>
                            <option value="linkedin">LinkedIn</option>
                            <option value="youtube">YouTube</option>
                            <option value="tiktok">TikTok</option>
                        </select>
                    </div>

                    <div v-if="connectForm.platform_type === 'facebook'" class="space-y-5">
                        <div>
                            <label class="label">Page ID</label>
                            <input v-model="connectForm.credentials.page_id" type="text" class="input" required />
                        </div>
                        <div>
                            <label class="label">Access token</label>
                            <input v-model="connectForm.credentials.access_token" type="text" class="input" required />
                        </div>
                        <div>
                            <label class="label">App secret (optional)</label>
                            <input v-model="connectForm.credentials.app_secret" type="text" class="input" />
                        </div>
                    </div>

                    <div v-if="connectForm.platform_type === 'linkedin'" class="space-y-5">
                        <div>
                            <button type="button" @click="loginWithLinkedIn" class="btn w-full bg-[#0a66c2] text-white hover:bg-[#084d92]" :disabled="linkingLinkedIn">
                                <PlatformIcon type="linkedin" :size="18" class="rounded-sm bg-white" />
                                <span v-if="!linkingLinkedIn">Log in with LinkedIn</span>
                                <span v-else>Connecting...</span>
                            </button>
                            <p class="hint">Connect your LinkedIn account automatically (recommended).</p>
                        </div>

                        <div class="flex items-center gap-3 text-xs font-medium uppercase tracking-wide text-slate-400">
                            <span class="h-px flex-1 bg-slate-200"></span> or enter manually <span class="h-px flex-1 bg-slate-200"></span>
                        </div>

                        <div>
                            <label class="label">Access token</label>
                            <input v-model="connectForm.credentials.access_token" type="text" class="input" placeholder="Enter access token manually" />
                            <p class="hint">Your LinkedIn OAuth access token, if you prefer manual entry.</p>
                        </div>
                        <div>
                            <label class="label">Person URN (optional)</label>
                            <input v-model="connectForm.credentials.person_urn" type="text" class="input" placeholder="urn:li:person:xxxxx" />
                            <p class="hint">If not provided, it is fetched automatically from your profile.</p>
                        </div>
                    </div>

                    <div v-if="connectForm.platform_type === 'youtube'">
                        <label class="label">Credentials JSON</label>
                        <textarea v-model="connectForm.credentials.credentials" rows="6" class="input font-mono text-xs" required placeholder="Paste your Google OAuth credentials JSON"></textarea>
                    </div>

                    <div v-if="connectForm.platform_type === 'tiktok'" class="space-y-5">
                        <div>
                            <label class="label">Access token</label>
                            <input v-model="connectForm.credentials.access_token" type="text" class="input" required />
                        </div>
                        <div>
                            <label class="label">Advertiser ID</label>
                            <input v-model="connectForm.credentials.advertiser_id" type="text" class="input" required />
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="button" @click="testConnection" class="btn btn-secondary" :disabled="loading || !connectForm.platform_type || !hasCredentials">
                            {{ testingConnection ? 'Testing...' : 'Test connection' }}
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="loading">
                            {{ loading ? 'Connecting...' : 'Connect platform' }}
                        </button>
                    </div>

                    <div v-if="testResult" class="alert" :class="testResult.success ? 'alert-success' : 'alert-error'">
                        {{ testResult.message }}
                    </div>
                    <div v-if="error" class="alert alert-error">{{ error }}</div>
                    <div v-if="success" class="alert alert-success">{{ success }}</div>
                </form>
            </section>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { Plug } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import PlatformIcon from '../components/PlatformIcon.vue';
import { confirmDialog } from '../feedback.js';
import { platformLabel } from '../platforms.js';

export default {
    name: 'Settings',
    components: { Plug, PageHeader, PlatformIcon },
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
                default:
                    return false;
            }
        });

        const disconnectPlatform = async (platformType) => {
            const confirmed = await confirmDialog({
                title: `Disconnect ${platformLabel(platformType)}?`,
                message: 'You will not be able to publish to this platform until you reconnect it.',
                confirmText: 'Disconnect',
                danger: true,
            });
            if (!confirmed) return;

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
            platformLabel,
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
