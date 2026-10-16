<template>
    <div>
        <PageHeader title="Content pipeline" description="Connect the Claude worker and ElevenLabs, and set the daily video times." />
        <AdminNav />

        <div v-if="loading" class="space-y-4">
            <div class="card h-40 animate-pulse bg-slate-100"></div>
            <div class="card h-60 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else-if="data" class="space-y-5">
            <p v-if="error" class="alert alert-error">{{ error }}</p>

            <PipelineChecks :checks="data.checks" />

            <!-- Claude worker -->
            <section class="card p-5">
                <h2 class="flex items-center gap-2 font-semibold text-slate-900"><Server class="h-4 w-4 text-slate-400" /> Claude worker</h2>
                <p class="mt-1 text-sm text-slate-600">The cloud server that researches, writes and edits (document 02). The secret signs every request in both directions.</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="label" for="worker-url">Worker URL</label>
                        <input id="worker-url" v-model="form.worker_url" type="url" class="input" placeholder="https://worker.yourdomain.com" />
                        <p v-if="data.fields.worker_url.source === 'default' && data.fields.worker_url.has_value" class="hint">From the server .env file.</p>
                    </div>
                    <div>
                        <label class="label" for="worker-secret">Shared secret</label>
                        <input id="worker-secret" v-model="form.worker_secret" type="password" autocomplete="off" class="input"
                            :placeholder="secretPlaceholder('worker_secret')" />
                        <p v-if="data.fields.worker_secret.source === 'default' && data.fields.worker_secret.has_value" class="hint">From the server .env file.</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button type="button" class="btn btn-primary" :disabled="busy" @click="save"><Save class="h-4 w-4" /> Save</button>
                    <button type="button" class="btn btn-secondary" :disabled="busy" @click="test('worker')"><PlugZap class="h-4 w-4" /> Save and test</button>
                </div>
                <p v-if="results.worker" class="alert mt-4" :class="results.worker.success ? 'alert-success' : 'alert-error'">{{ results.worker.message }}</p>
            </section>

            <!-- ElevenLabs -->
            <section class="card p-5">
                <h2 class="flex items-center gap-2 font-semibold text-slate-900"><AudioLines class="h-4 w-4 text-slate-400" /> ElevenLabs voice</h2>
                <p class="mt-1 text-sm text-slate-600">Turns the approved script into your cloned voice (document 03). Use a key limited to Text to Speech, Voices (read) and User (read).</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label" for="el-key">API key</label>
                        <input id="el-key" v-model="form.elevenlabs_key" type="password" autocomplete="off" class="input"
                            :placeholder="secretPlaceholder('elevenlabs_key')" />
                        <p v-if="data.fields.elevenlabs_key.source === 'default' && data.fields.elevenlabs_key.has_value" class="hint">From the server .env file.</p>
                    </div>
                    <div>
                        <label class="label" for="el-voice">Voice ID</label>
                        <input id="el-voice" v-model="form.elevenlabs_voice_id" class="input" placeholder="From the voice's page in ElevenLabs" />
                    </div>
                    <div>
                        <label class="label" for="el-model">Model ID</label>
                        <input id="el-model" v-model="form.elevenlabs_model_id" class="input" placeholder="eleven_v3" />
                    </div>
                    <div v-for="s in voiceSettings" :key="s.key">
                        <label class="label" :for="'el-' + s.key">{{ s.label }} <span class="font-normal text-slate-400">({{ s.hint }})</span></label>
                        <input :id="'el-' + s.key" v-model="form[s.key]" type="number" min="0" max="1" step="0.05" class="input" />
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button type="button" class="btn btn-primary" :disabled="busy" @click="save"><Save class="h-4 w-4" /> Save</button>
                    <button type="button" class="btn btn-secondary" :disabled="busy" @click="test('elevenlabs')"><PlugZap class="h-4 w-4" /> Save and test</button>
                </div>

                <div v-if="results.elevenlabs" class="mt-4">
                    <p class="alert" :class="results.elevenlabs.success ? 'alert-success' : 'alert-error'">{{ results.elevenlabs.message }}</p>
                    <dl v-if="results.elevenlabs.details" class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <template v-if="results.elevenlabs.details.characters_limit != null">
                            <div>
                                <dt class="text-slate-500">Plan</dt>
                                <dd class="font-medium capitalize text-slate-900">{{ results.elevenlabs.details.tier || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Characters left this month</dt>
                                <dd class="font-medium text-slate-900">
                                    {{ (results.elevenlabs.details.characters_limit - results.elevenlabs.details.characters_used).toLocaleString() }}
                                    of {{ results.elevenlabs.details.characters_limit.toLocaleString() }}
                                </dd>
                            </div>
                        </template>
                        <div v-if="results.elevenlabs.details.voice">
                            <dt class="text-slate-500">Voice</dt>
                            <dd class="font-medium text-slate-900">{{ results.elevenlabs.details.voice }} <span class="text-slate-400">({{ results.elevenlabs.details.voice_category }})</span></dd>
                        </div>
                        <div v-if="results.elevenlabs.details.urdu != null">
                            <dt class="text-slate-500">Urdu on this model</dt>
                            <dd class="font-medium" :class="results.elevenlabs.details.urdu ? 'text-emerald-700' : 'text-amber-700'">{{ results.elevenlabs.details.urdu ? 'Supported' : 'Not listed' }}</dd>
                        </div>
                        <p v-if="results.elevenlabs.details.account_note" class="text-slate-500 sm:col-span-2">{{ results.elevenlabs.details.account_note }}</p>
                    </dl>
                </div>
            </section>

            <!-- Series A times -->
            <section class="card p-5">
                <h2 class="flex items-center gap-2 font-semibold text-slate-900"><Clock class="h-4 w-4 text-slate-400" /> Daily video (Series A)</h2>
                <p class="mt-1 text-sm text-slate-600">When research starts each day and the default time the posts go out. You can still change the publish time on each episode.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="s-research">Research starts at</label>
                        <input id="s-research" v-model="form.series_research_at" type="time" class="input" />
                    </div>
                    <div>
                        <label class="label" for="s-publish">Publish at</label>
                        <input id="s-publish" v-model="form.series_publish_at" type="time" class="input" />
                    </div>
                    <div>
                        <label class="label" for="s-tz">Timezone</label>
                        <input id="s-tz" v-model="form.series_timezone" class="input" list="timezones" placeholder="Asia/Karachi" />
                        <datalist id="timezones"><option v-for="tz in timezones" :key="tz" :value="tz"></option></datalist>
                    </div>
                    <div>
                        <label class="label" for="s-shorts">Shorts per episode</label>
                        <input id="s-shorts" v-model="form.series_shorts" type="number" min="0" max="6" class="input" />
                    </div>
                </div>

                <div class="mt-5">
                    <button type="button" class="btn btn-primary" :disabled="busy" @click="save"><Save class="h-4 w-4" /> Save</button>
                </div>
            </section>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { AudioLines, Clock, PlugZap, Save, Server } from 'lucide-vue-next';
import AdminNav from '../components/AdminNav.vue';
import PageHeader from '../components/PageHeader.vue';
import PipelineChecks from '../components/PipelineChecks.vue';
import { toast } from '../feedback.js';

// text fields that map straight to a saved setting
const TEXT_KEYS = [
    'worker_url', 'elevenlabs_voice_id', 'elevenlabs_model_id',
    'elevenlabs_stability', 'elevenlabs_similarity_boost', 'elevenlabs_style',
    'series_research_at', 'series_publish_at', 'series_timezone', 'series_shorts',
];
const SECRET_KEYS = ['worker_secret', 'elevenlabs_key'];

export default {
    name: 'AdminPipeline',
    components: { AdminNav, AudioLines, Clock, PageHeader, PipelineChecks, PlugZap, Save, Server },
    setup() {
        const data = ref(null);
        const form = ref({});
        const results = ref({});
        const loading = ref(true);
        const busy = ref(false);
        const error = ref('');
        const timezones = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : ['Asia/Karachi', 'UTC'];
        const voiceSettings = [
            { key: 'elevenlabs_stability', label: 'Stability', hint: '0 to 1' },
            { key: 'elevenlabs_similarity_boost', label: 'Similarity', hint: '0 to 1' },
            { key: 'elevenlabs_style', label: 'Style', hint: '0 to 1' },
        ];

        const fill = (payload) => {
            data.value = payload;
            const next = {};
            TEXT_KEYS.forEach(k => { next[k] = payload.fields[k].value ?? ''; });
            SECRET_KEYS.forEach(k => { next[k] = ''; });
            form.value = next;
        };

        const secretPlaceholder = (key) => {
            const f = data.value.fields[key];
            return f.has_value ? `Saved (${f.hint}). Type to replace.` : 'Paste it here';
        };

        const load = async () => {
            try {
                const { data: payload } = await axios.get('/admin/pipeline');
                fill(payload);
            } catch (err) {
                error.value = err.response?.data?.message || 'Could not load the settings.';
            } finally {
                loading.value = false;
            }
        };

        // Only send what differs from what is shown, so a normal field left as its .env default stays a default
        const save = async (quiet = false) => {
            busy.value = true;
            error.value = '';
            try {
                const values = {};
                for (const k of [...TEXT_KEYS, ...SECRET_KEYS]) {
                    const shown = data.value.fields[k].value ?? '';
                    if (SECRET_KEYS.includes(k) ? form.value[k] : String(form.value[k]) !== String(shown)) {
                        values[k] = form.value[k];
                    }
                }
                const { data: payload } = await axios.put('/admin/pipeline', { values });
                fill(payload);
                if (quiet !== true) toast('Settings saved', 'success');
                return true;
            } catch (err) {
                const body = err.response?.data || {};
                error.value = body.errors ? Object.values(body.errors).flat().join('\n') : body.message || 'Could not save the settings.';
                return false;
            } finally {
                busy.value = false;
            }
        };

        const test = async (service) => {
            if (!(await save(true))) return;
            busy.value = true;
            results.value = { ...results.value, [service]: null };
            try {
                const { data: result } = await axios.post(`/admin/pipeline/test/${service}`);
                results.value = { ...results.value, [service]: result };
                const { data: payload } = await axios.get('/admin/pipeline');
                data.value = { ...data.value, checks: payload.checks };
            } catch (err) {
                results.value = { ...results.value, [service]: { success: false, message: err.response?.data?.message || 'The test failed.' } };
            } finally {
                busy.value = false;
            }
        };

        onMounted(load);

        return { data, form, results, loading, busy, error, timezones, voiceSettings, secretPlaceholder, save, test };
    },
};
</script>

<style scoped>
.hint { margin-top: 0.375rem; font-size: 0.75rem; color: #64748b; }
</style>
