<template>
    <div>
        <button class="btn btn-ghost -ml-3 mb-4 px-3! py-1.5!" @click="goBack">
            <ArrowLeft class="h-4 w-4" /> Back
        </button>

        <div v-if="loading" class="space-y-4">
            <div class="card h-24 animate-pulse bg-slate-100"></div>
            <div class="card h-72 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else-if="!episode" class="card px-5 py-14 text-center">
            <p class="font-medium text-slate-900">Episode not found</p>
            <router-link to="/episodes" class="btn btn-primary mt-5">Back to episodes</router-link>
        </div>

        <template v-else>
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ episode.topic || kindLabel(episode.kind) }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ kindLabel(episode.kind) }} &middot; {{ episode.run_date }}</p>
                </div>
                <span class="badge" :class="statusBadge(episode.status)">{{ statusLabel(episode.status) }}</span>
            </div>

            <p v-if="error" class="alert alert-error mb-5">{{ error }}</p>

            <!-- Publish time: any open episode -->
            <section v-if="canSetTime" class="card mb-6 p-5">
                <label class="label" for="ep-when">Publish at</label>
                <div class="flex flex-wrap items-center gap-3">
                    <input id="ep-when" v-model="when" type="datetime-local" class="input sm:max-w-xs" />
                    <button type="button" class="btn btn-secondary" :disabled="busy || !whenChanged" @click="saveTime">Save time</button>
                    <button type="button" class="btn btn-ghost" :disabled="busy" @click="publishTomorrow">Publish tomorrow</button>
                </div>
                <p class="mt-2 text-xs text-slate-500">If the episode is not ready 15 minutes before this time, the day is skipped and nothing is posted.</p>
            </section>
            <p v-else-if="episode.scheduled_for" class="mb-6 text-sm text-slate-600">Publishes {{ new Date(episode.scheduled_for).toLocaleString() }}</p>

            <p v-if="working" class="alert alert-info mb-6">
                <Loader2 class="mt-0.5 h-4 w-4 shrink-0 animate-spin" /> {{ statusLabel(episode.status) }}. This page updates by itself.
            </p>
            <p v-if="stalled" class="alert alert-warning mb-6">
                This has been waiting for over a minute, so the queue worker is probably not running: nothing is picking up the job.
                Start it (<code>php artisan queue:work</code>) and check Admin &gt; Content pipeline.
            </p>
            <p v-if="episode.status === 'failed'" class="alert alert-error mb-6">{{ episode.error }}</p>
            <p v-if="episode.status === 'skipped'" class="alert alert-warning mb-6">{{ episode.error }}</p>

            <section v-if="episode.prompt" class="card mb-6 p-5">
                <h2 class="label">Request</h2>
                <p class="whitespace-pre-line text-sm text-slate-700" dir="auto">{{ episode.prompt }}</p>
            </section>

            <section v-if="episode.outputs?.image" class="card mb-6 p-5">
                <h2 class="label">Image</h2>
                <img :src="episode.outputs.image.url" alt="Generated image" class="max-h-96 rounded-lg" />
            </section>

            <!-- Script (video) or post text -->
            <section v-if="episode.script_ur" class="card mb-6 p-5">
                <h2 class="label">{{ isVideo ? 'Urdu script' : 'Post text' }}</h2>
                <textarea v-model="script" dir="auto" :rows="isVideo ? 16 : 8" class="input leading-loose" :readonly="episode.status !== 'awaiting_approval'" :style="isVideo ? 'font-size: 1.1rem' : ''"></textarea>
                <p class="mt-1.5 text-right text-xs text-slate-400">{{ script.length }} characters</p>

                <div v-if="episode.status === 'awaiting_approval'" class="mt-4 space-y-4">
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="btn btn-primary" :disabled="busy" @click="approve">
                            <Check class="h-4 w-4" /> {{ approveLabel }}
                        </button>
                        <button type="button" class="btn btn-secondary" :disabled="busy || script === episode.script_ur" @click="saveScript">Save edits only</button>
                    </div>

                    <div>
                        <label class="label" for="ep-note">Ask for a change, or reject</label>
                        <input id="ep-note" v-model="note" class="input" placeholder="e.g. make the intro shorter, or: pick a different story" maxlength="1000" />
                        <div class="mt-3 flex flex-wrap gap-3">
                            <button type="button" class="btn btn-secondary" :disabled="busy || !note.trim()" @click="revise">Revise script with this instruction</button>
                            <button type="button" class="btn btn-secondary" :disabled="busy || !note.trim()" @click="reject(true)">Reject, start over</button>
                            <button type="button" class="btn btn-danger-soft" :disabled="busy || !note.trim()" @click="reject(false)">Reject, stop today</button>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="episode.sources?.length" class="card mb-6 p-5">
                <h2 class="label">Sources</h2>
                <ul class="space-y-3">
                    <li v-for="(s, i) in episode.sources" :key="i" class="text-sm">
                        <a :href="s.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-medium text-brand-700 hover:text-brand-800">
                            {{ s.title || s.url }} <ExternalLink class="h-3.5 w-3.5" />
                        </a>
                        <p v-if="s.claim" class="text-slate-600">{{ s.claim }}</p>
                    </li>
                </ul>
            </section>

            <!-- Voice + avatar -->
            <section v-if="episode.voice_url" class="card mb-6 p-5">
                <h2 class="label">Voice</h2>
                <audio :src="episode.voice_url" controls class="w-full"></audio>
                <a :href="episode.voice_url + '&download=1'" class="btn btn-secondary btn-sm mt-3"><Download class="h-4 w-4" /> Download voice.mp3</a>
            </section>

            <section v-if="episode.status === 'awaiting_avatar'" class="card mb-6 p-5">
                <h2 class="label">Avatar clip</h2>
                <p class="mb-3 text-sm text-slate-600">Make the avatar video in ElevenLabs from the voice file above, then upload it here (MP4 or MOV). The edit starts right after.</p>
                <input ref="avatarInput" type="file" accept="video/mp4,video/quicktime" class="input" :disabled="busy" @change="uploadAvatar" />
                <div v-if="progress > 0" class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full bg-brand-600 transition-all" :style="{ width: progress + '%' }"></div>
                </div>
            </section>

            <!-- Outputs: final approval -->
            <section v-if="episode.outputs" class="card mb-6 p-5">
                <h2 class="label">Videos</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-if="episode.outputs.long">
                        <p class="mb-1 text-sm font-medium text-slate-800">{{ episode.outputs.titles?.long || 'Long video' }}</p>
                        <video :src="episode.outputs.long.url" controls class="w-full rounded-lg bg-black"></video>
                    </div>
                    <div v-for="(s, i) in episode.outputs.shorts" :key="i">
                        <p class="mb-1 text-sm font-medium text-slate-800">{{ episode.outputs.titles?.shorts?.[i] || 'Short ' + (i + 1) }}</p>
                        <video v-if="s" :src="s.url" controls class="max-h-96 rounded-lg bg-black"></video>
                    </div>
                    <div v-if="episode.outputs.thumbnail">
                        <p class="mb-1 text-sm font-medium text-slate-800">Thumbnail</p>
                        <img :src="episode.outputs.thumbnail.url" alt="Thumbnail" class="w-full rounded-lg" />
                    </div>
                </div>

                <div v-if="episode.status === 'ready'" class="mt-5">
                    <p class="mb-3 text-sm text-slate-600">Nothing is posted until you approve. Check the publish time above, then:</p>
                    <button type="button" class="btn btn-primary" :disabled="busy" @click="approveOutputs"><Check class="h-4 w-4" /> Approve and schedule posts</button>
                </div>
                <div v-if="episode.status === 'scheduled'" class="mt-5">
                    <router-link to="/posts?status=scheduled" class="btn btn-secondary mr-3">See scheduled posts</router-link>
                    <button type="button" class="btn btn-danger-soft" :disabled="busy" @click="cancelPosting">Cancel posting</button>
                </div>
            </section>

            <div v-if="episode.status === 'scheduled' && !episode.outputs?.long" class="mb-6">
                <router-link to="/posts?status=scheduled" class="btn btn-secondary mr-3">See scheduled posts</router-link>
                <button type="button" class="btn btn-danger-soft" :disabled="busy" @click="cancelPosting">Cancel posting</button>
            </div>

            <div class="mb-6 flex flex-wrap gap-3">
                <button v-if="episode.status === 'failed'" type="button" class="btn btn-primary" :disabled="busy" @click="act('retry')"><RotateCcw class="h-4 w-4" /> Retry</button>
                <button v-if="canCancel" type="button" class="btn btn-danger-soft" :disabled="busy" @click="cancelEpisode">Stop this episode</button>
            </div>

            <!-- History -->
            <section v-if="episode.logs?.length" class="card p-5">
                <h2 class="label">History</h2>
                <ul class="space-y-2 text-sm">
                    <li v-for="l in episode.logs" :key="l.id" class="flex gap-3">
                        <span class="w-36 shrink-0 text-xs text-slate-400">{{ new Date(l.created_at).toLocaleString() }}</span>
                        <span class="font-medium capitalize" :class="l.status === 'failed' ? 'text-red-700' : 'text-slate-800'">{{ l.step }}</span>
                        <span class="min-w-0 break-words text-slate-600">{{ l.message }}</span>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>

<script>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { ArrowLeft, Check, Download, ExternalLink, Loader2, RotateCcw } from 'lucide-vue-next';
import { confirmDialog, toast } from '../feedback.js';
import { BUSY, kindLabel, statusBadge, statusLabel } from '../episodes.js';

// ISO (UTC) <-> the value a datetime-local input uses (local time)
const toLocalInput = (iso) => {
    const d = new Date(iso);
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
};

export default {
    name: 'EpisodeDetail',
    components: { ArrowLeft, Check, Download, ExternalLink, Loader2, RotateCcw },
    setup() {
        const route = useRoute();
        const router = useRouter();
        const episode = ref(null);
        const loading = ref(true);
        const busy = ref(false);
        const error = ref('');
        const script = ref('');
        const note = ref('');
        const when = ref('');
        const progress = ref(0);
        const avatarInput = ref(null);
        let timer = null;

        const isVideo = computed(() => episode.value?.kind === 'video');
        const approveLabel = computed(() => {
            const edited = script.value !== episode.value?.script_ur;
            if (isVideo.value) return edited ? 'Save edits and approve' : 'Approve';
            return edited ? 'Save edits, approve and schedule' : 'Approve and schedule';
        });
        const working = computed(() => BUSY.includes(episode.value?.status));
        const stalled = computed(() => episode.value?.status === 'pending' && Date.now() - new Date(episode.value.updated_at).getTime() > 60000);
        const canSetTime = computed(() => ['pending', 'researching', 'awaiting_approval', 'approved', 'voicing', 'awaiting_avatar', 'editing', 'importing', 'ready', 'failed'].includes(episode.value?.status));
        const canCancel = computed(() => canSetTime.value);
        const whenChanged = computed(() => episode.value?.scheduled_for && when.value !== toLocalInput(episode.value.scheduled_for));

        const apply = (data, keepEdits = false) => {
            const typed = script.value;
            const wasEdited = keepEdits && episode.value && typed !== episode.value.script_ur;
            episode.value = data;
            script.value = wasEdited ? typed : data.script_ur || '';
            when.value = data.scheduled_for ? toLocalInput(data.scheduled_for) : '';
        };

        const load = async (silent = false) => {
            try {
                const { data } = await axios.get(`/episodes/${route.params.id}`);
                // a background refresh must not wipe what is being typed, nor the time being picked
                const pickedTime = when.value;
                const timeEdited = silent && episode.value && pickedTime !== toLocalInput(episode.value.scheduled_for);
                apply(data, silent);
                if (timeEdited) when.value = pickedTime;
            } catch (err) {
                if (!silent) episode.value = null;
            } finally {
                loading.value = false;
            }
        };

        const run = async (request, success) => {
            busy.value = true;
            error.value = '';
            try {
                const { data } = await request();
                apply(data);
                note.value = '';
                if (success) toast(success, 'success');
            } catch (err) {
                const data = err.response?.data || {};
                error.value = data.errors ? Object.values(data.errors).flat().join('\n') : data.message || 'That did not work.';
            } finally {
                busy.value = false;
            }
        };

        const id = () => route.params.id;
        const act = (name, body = {}, success = '') => run(() => axios.post(`/episodes/${id()}/${name}`, body), success);

        const approve = () => act('approve', { script_ur: script.value }, isVideo.value ? 'Approved: making the voice' : 'Approved: posts scheduled');
        const saveScript = () => run(() => axios.patch(`/episodes/${id()}`, { script_ur: script.value }), 'Script saved');
        const revise = () => act('revise', { instruction: note.value }, 'Asked the worker to revise the script');
        const reject = (retry) => act('reject', { note: note.value, retry }, retry ? 'Rejected: researching another story' : 'Rejected');
        const approveOutputs = () => act('approve-outputs', {}, 'Scheduling the posts');

        const saveTime = () => run(() => axios.patch(`/episodes/${id()}`, { scheduled_for: new Date(when.value).toISOString() }), 'Publish time saved');

        const publishTomorrow = () => {
            const d = new Date(episode.value.scheduled_for);
            d.setDate(d.getDate() + 1);
            when.value = toLocalInput(d.toISOString());
            return saveTime();
        };

        const cancelPosting = async () => {
            const ok = await confirmDialog({ title: 'Cancel posting?', message: 'The scheduled posts are removed. The videos stay and you can approve again.', confirmText: 'Cancel posting', danger: true });
            if (ok) await act('cancel-posting', {}, 'Posting cancelled');
        };

        const cancelEpisode = async () => {
            const ok = await confirmDialog({ title: 'Stop this episode?', message: 'Nothing will be posted for it.', confirmText: 'Stop episode', danger: true });
            if (ok) await act('cancel', {}, 'Episode stopped');
        };

        const uploadAvatar = async (event) => {
            const file = event.target.files[0];
            if (!file) return;
            const form = new FormData();
            form.append('file', file);
            busy.value = true;
            error.value = '';
            progress.value = 1;
            try {
                const { data } = await axios.post(`/episodes/${id()}/avatar`, form, {
                    timeout: 0,
                    onUploadProgress: (e) => {
                        progress.value = e.total ? Math.max(1, Math.round((e.loaded / e.total) * 100)) : 1;
                    },
                });
                apply(data);
                toast('Avatar uploaded: editing the video', 'success');
            } catch (err) {
                const data = err.response?.data || {};
                error.value = data.errors ? Object.values(data.errors).flat().join('\n') : data.message || 'The upload failed.';
                if (avatarInput.value) avatarInput.value.value = '';
            } finally {
                progress.value = 0;
                busy.value = false;
            }
        };

        const goBack = () => (window.history.length > 1 ? router.back() : router.push('/episodes'));

        onMounted(async () => {
            await load();
            timer = setInterval(() => {
                if (!busy.value && working.value) load(true);
            }, 5000);
        });
        onBeforeUnmount(() => clearInterval(timer));

        return {
            episode, isVideo, approveLabel, loading, busy, error, script, note, when, progress, avatarInput, working, stalled, canSetTime, canCancel, whenChanged,
            act, approve, saveScript, revise, reject, approveOutputs, saveTime, publishTomorrow, cancelPosting, cancelEpisode, uploadAvatar, goBack,
            kindLabel, statusBadge, statusLabel,
        };
    },
};
</script>
