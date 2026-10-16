<template>
    <div>
        <PageHeader title="Profile" description="Your name, photo and password." />

        <div class="space-y-6">
            <!-- Photo -->
            <section class="card p-5">
                <h2 class="label">Profile picture</h2>
                <div class="flex flex-wrap items-center gap-5">
                    <img v-if="user.avatar_url" :src="user.avatar_url" alt="Your profile picture" class="h-24 w-24 rounded-full object-cover ring-1 ring-slate-200" />
                    <div v-else class="flex h-24 w-24 items-center justify-center rounded-full bg-brand-100 text-3xl font-semibold text-brand-700">{{ initial }}</div>
                    <div>
                        <div class="flex flex-wrap gap-2">
                            <label class="btn btn-secondary cursor-pointer" :class="{ 'pointer-events-none opacity-60': avatarBusy }">
                                <Camera class="h-4 w-4" /> {{ avatarBusy ? 'Uploading...' : user.avatar_url ? 'Change photo' : 'Upload photo' }}
                                <input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp" :disabled="avatarBusy" @change="uploadAvatar" />
                            </label>
                            <button v-if="user.avatar_url" type="button" class="btn btn-danger-soft" :disabled="avatarBusy" @click="removeAvatar">
                                <Trash2 class="h-4 w-4" /> Remove
                            </button>
                        </div>
                        <p class="hint">JPG, PNG or WebP, up to 2MB. It is cropped to a square.</p>
                        <p v-if="avatarError" class="mt-2 text-sm text-red-700">{{ avatarError }}</p>
                    </div>
                </div>
            </section>

            <!-- Name -->
            <form class="card space-y-4 p-5" @submit.prevent="saveName">
                <h2 class="label">Details</h2>
                <div>
                    <label class="label" for="profile-name">Name</label>
                    <input id="profile-name" v-model="name" type="text" class="input" autocomplete="name" maxlength="255" required />
                </div>
                <div>
                    <label class="label" for="profile-email">Email</label>
                    <input id="profile-email" :value="user.email" type="email" class="input" disabled />
                    <p class="hint">Your email is your login and can't be changed here.</p>
                </div>
                <p v-if="nameError" class="text-sm text-red-700">{{ nameError }}</p>
                <button type="submit" class="btn btn-primary" :disabled="nameBusy || !name.trim() || name.trim() === user.name">
                    <Save class="h-4 w-4" /> {{ nameBusy ? 'Saving...' : 'Save name' }}
                </button>
            </form>

            <!-- Password -->
            <form class="card space-y-4 p-5" @submit.prevent="savePassword">
                <h2 class="label">Change password</h2>
                <div>
                    <label class="label">Current password</label>
                    <PasswordInput v-model="pw.current_password" name="current_password" autocomplete="current-password" />
                    <p v-if="pwErrors.current_password" class="mt-1.5 text-xs font-medium text-red-600">{{ pwErrors.current_password }}</p>
                </div>
                <div>
                    <label class="label">New password</label>
                    <PasswordInput v-model="pw.password" name="password" autocomplete="new-password" />
                    <ul class="mt-3 grid gap-1.5 sm:grid-cols-2">
                        <li v-for="rule in rules" :key="rule.label" class="flex items-center gap-2 text-xs" :class="rule.ok ? 'text-emerald-700' : 'text-slate-500'">
                            <CircleCheck v-if="rule.ok" class="h-4 w-4 shrink-0" />
                            <Circle v-else class="h-4 w-4 shrink-0" />
                            {{ rule.label }}
                        </li>
                    </ul>
                    <p v-if="pwErrors.password" class="mt-1.5 text-xs font-medium text-red-600">{{ pwErrors.password }}</p>
                </div>
                <div>
                    <label class="label">Confirm new password</label>
                    <PasswordInput v-model="pw.password_confirmation" name="password_confirmation" autocomplete="new-password" />
                    <p v-if="pw.password_confirmation" class="mt-1.5 text-xs font-medium" :class="pw.password === pw.password_confirmation ? 'text-emerald-700' : 'text-red-600'">
                        {{ pw.password === pw.password_confirmation ? 'Passwords match' : 'Passwords do not match' }}
                    </p>
                </div>
                <p v-if="pwError" class="text-sm text-red-700">{{ pwError }}</p>
                <button type="submit" class="btn btn-primary" :disabled="pwBusy || !canSubmitPassword">
                    <KeyRound class="h-4 w-4" /> {{ pwBusy ? 'Changing...' : 'Change password' }}
                </button>
                <p class="hint">Changing your password signs you out of your other devices.</p>
            </form>
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { Camera, Circle, CircleCheck, KeyRound, Save, Trash2 } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import PasswordInput from '../components/PasswordInput.vue';
import { toast } from '../feedback.js';

export default {
    name: 'Profile',
    components: { Camera, Circle, CircleCheck, KeyRound, Save, Trash2, PageHeader, PasswordInput },
    setup() {
        const readStored = () => {
            try {
                return JSON.parse(localStorage.getItem('user') || 'null') || {};
            } catch (e) {
                return {};
            }
        };
        const user = ref(readStored());
        const name = ref(user.value.name || '');
        const initial = computed(() => (user.value.name || user.value.email || '?').charAt(0).toUpperCase());

        // keep the sidebar (App.vue) in sync
        const setUser = (data) => {
            user.value = data;
            localStorage.setItem('user', JSON.stringify(data));
            window.dispatchEvent(new Event('user-updated'));
        };

        onMounted(async () => {
            try {
                const { data } = await axios.get('/auth/me');
                setUser(data);
                name.value = data.name || '';
            } catch (e) {
                // the stored copy is shown instead
            }
        });

        // --- name
        const nameBusy = ref(false);
        const nameError = ref('');
        const saveName = async () => {
            nameBusy.value = true;
            nameError.value = '';
            try {
                const { data } = await axios.put('/auth/profile', { name: name.value.trim() });
                setUser(data);
                name.value = data.name;
                toast('Name updated', 'success');
            } catch (err) {
                nameError.value = err.response?.data?.errors?.name?.[0] || err.response?.data?.message || 'Could not save your name.';
            } finally {
                nameBusy.value = false;
            }
        };

        // --- avatar
        const avatarBusy = ref(false);
        const avatarError = ref('');
        const uploadAvatar = async (event) => {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;
            avatarError.value = '';
            if (file.size > 2 * 1024 * 1024) {
                avatarError.value = 'This image is too large (max 2MB).';
                return;
            }
            avatarBusy.value = true;
            try {
                const formData = new FormData();
                formData.append('avatar', file);
                const { data } = await axios.post('/auth/avatar', formData);
                setUser(data);
                toast('Profile picture updated', 'success');
            } catch (err) {
                avatarError.value = err.response?.data?.errors?.avatar?.[0] || err.response?.data?.message || 'Could not upload the picture.';
            } finally {
                avatarBusy.value = false;
            }
        };
        const removeAvatar = async () => {
            avatarBusy.value = true;
            avatarError.value = '';
            try {
                const { data } = await axios.delete('/auth/avatar');
                setUser(data);
                toast('Profile picture removed', 'success');
            } catch (err) {
                avatarError.value = err.response?.data?.message || 'Could not remove the picture.';
            } finally {
                avatarBusy.value = false;
            }
        };

        // --- password (same rules as registration)
        const pw = reactive({ current_password: '', password: '', password_confirmation: '' });
        const pwBusy = ref(false);
        const pwError = ref('');
        const pwErrors = ref({});
        const rules = computed(() => [
            { label: 'At least 8 characters', ok: pw.password.length >= 8 },
            { label: 'Upper and lower case', ok: /[a-z]/.test(pw.password) && /[A-Z]/.test(pw.password) },
            { label: 'A number', ok: /\d/.test(pw.password) },
            { label: 'A symbol', ok: /[^A-Za-z0-9]/.test(pw.password) },
        ]);
        const canSubmitPassword = computed(() => pw.current_password && rules.value.every(r => r.ok) && pw.password === pw.password_confirmation);
        const savePassword = async () => {
            pwBusy.value = true;
            pwError.value = '';
            pwErrors.value = {};
            try {
                await axios.put('/auth/password', { ...pw });
                pw.current_password = pw.password = pw.password_confirmation = '';
                toast('Password changed', 'success');
            } catch (err) {
                const errors = err.response?.data?.errors || {};
                pwErrors.value = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]));
                if (!Object.keys(pwErrors.value).length) {
                    pwError.value = err.response?.status === 429 ? 'Too many attempts. Please wait a minute.' : (err.response?.data?.message || 'Could not change the password.');
                }
            } finally {
                pwBusy.value = false;
            }
        };

        return {
            user, name, initial, nameBusy, nameError, saveName, avatarBusy, avatarError, uploadAvatar, removeAvatar,
            pw, pwBusy, pwError, pwErrors, rules, canSubmitPassword, savePassword,
        };
    },
};
</script>
