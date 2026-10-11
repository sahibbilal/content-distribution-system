<template>
    <div>
        <PageHeader title="Admin settings" description="Control sign-ups and how uploaded media is handled." />
        <AdminNav />

        <div v-if="loading" class="space-y-4">
            <div class="card h-28 animate-pulse bg-slate-100"></div>
            <div class="card h-28 animate-pulse bg-slate-100"></div>
        </div>

        <form v-else-if="form" class="max-w-3xl space-y-5" @submit.prevent="save">
            <section class="card p-5">
                <label class="flex cursor-pointer items-start justify-between gap-4">
                    <span>
                        <span class="flex items-center gap-2 font-semibold text-slate-900"><UserPlus class="h-4 w-4 text-slate-400" /> Allow new registrations</span>
                        <span class="mt-1 block text-sm text-slate-600">
                            When off, nobody can create an account (including through Facebook login) and the sign-up buttons
                            are hidden. Existing users can still log in. Turn it back on whenever you want.
                        </span>
                    </span>
                    <input v-model="form.registration_open" type="checkbox" class="peer sr-only" />
                    <span class="toggle" aria-hidden="true"></span>
                </label>
                <p class="mt-3 text-sm font-medium" :class="form.registration_open ? 'text-emerald-700' : 'text-amber-700'">
                    {{ form.registration_open ? 'Registration is open.' : 'Registration is closed.' }}
                </p>
            </section>

            <section class="card p-5">
                <h2 class="flex items-center gap-2 font-semibold text-slate-900"><Images class="h-4 w-4 text-slate-400" /> Uploaded media after publishing</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Each user only ever sees their own files, in their Media page.
                </p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="cursor-pointer">
                        <input v-model="form.delete_media_after_publish" type="radio" :value="true" class="peer sr-only" />
                        <span class="block rounded-lg p-4 ring-1 ring-inset ring-slate-300 transition peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-600">
                            <span class="flex items-center gap-2 font-medium text-slate-900"><Trash2 class="h-4 w-4" /> Delete after publishing</span>
                            <span class="mt-1 block text-sm text-slate-600">Files are removed once a post is published everywhere. Saves storage.</span>
                        </span>
                    </label>
                    <label class="cursor-pointer">
                        <input v-model="form.delete_media_after_publish" type="radio" :value="false" class="peer sr-only" />
                        <span class="block rounded-lg p-4 ring-1 ring-inset ring-slate-300 transition peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-600">
                            <span class="flex items-center gap-2 font-medium text-slate-900"><Database class="h-4 w-4" /> Keep for future use</span>
                            <span class="mt-1 block text-sm text-slate-600">Files stay in the user's media library so they can reuse them in new posts.</span>
                        </span>
                    </label>
                </div>
            </section>

            <div v-if="error" class="alert alert-error">{{ error }}</div>
            <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save settings' }}</button>
        </form>

        <div v-else class="alert alert-error">{{ error || 'Could not load the settings.' }}</div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { Database, Images, Trash2, UserPlus } from 'lucide-vue-next';
import AdminNav from '../components/AdminNav.vue';
import PageHeader from '../components/PageHeader.vue';
import { loadSiteContent, setSiteContent } from '../siteContent.js';
import { toast } from '../feedback.js';

export default {
    name: 'AdminSettings',
    components: { AdminNav, PageHeader, Database, Images, Trash2, UserPlus },
    setup() {
        const form = ref(null);
        const loading = ref(true);
        const saving = ref(false);
        const error = ref('');

        onMounted(async () => {
            try {
                const content = await loadSiteContent(true);
                form.value = {
                    registration_open: content.registration_open !== false,
                    delete_media_after_publish: content.delete_media_after_publish !== false,
                };
            } catch (err) {
                error.value = 'Could not load the settings.';
            } finally {
                loading.value = false;
            }
        });

        const save = async () => {
            saving.value = true;
            error.value = '';
            try {
                const response = await axios.put('/admin/site-content', {
                    registration_open: form.value.registration_open,
                    delete_media_after_publish: form.value.delete_media_after_publish,
                });
                setSiteContent(response.data);
                toast('Settings saved', 'success');
            } catch (err) {
                error.value = err.response?.data?.message || 'Could not save the settings.';
            } finally {
                saving.value = false;
            }
        };

        return { form, loading, saving, error, save };
    },
};
</script>
