<template>
    <AuthLayout :title="registrationClosed ? 'Registration is closed' : registered ? 'Check your email' : 'Create your account'" :subtitle="registered || registrationClosed ? '' : 'Start publishing to all your platforms in minutes.'">
        <template v-if="registrationClosed">
            <p class="text-sm leading-6 text-slate-600">New accounts can't be created right now. If you already have an account you can still log in.</p>
            <router-link to="/login" class="btn btn-primary mt-6 w-full">Go to login</router-link>
        </template>

        <template v-else-if="registered">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-100 text-brand-600">
                <MailCheck class="h-6 w-6" />
            </div>
            <p class="mt-4 text-sm leading-6 text-slate-600">
                We sent a verification link to <strong class="text-slate-900">{{ form.email }}</strong>.
                Click it to verify your address, then log in.
            </p>
            <div v-if="notice" class="alert alert-info mt-4">{{ notice }}</div>
            <button type="button" class="btn btn-primary mt-6 w-full" :disabled="resending" @click="resend">
                {{ resending ? 'Sending...' : 'Resend verification email' }}
            </button>
            <p class="mt-4 text-center text-sm">
                <router-link to="/login" class="font-semibold text-brand-700 hover:text-brand-800">Go to login</router-link>
            </p>
        </template>

        <form v-else class="space-y-5" @submit.prevent="handleRegister">
            <div>
                <label class="label">Name</label>
                <input v-model="form.name" type="text" autocomplete="name" class="input" required />
            </div>
            <div>
                <label class="label">Email</label>
                <input v-model="form.email" type="email" autocomplete="email" class="input" placeholder="you@example.com" required />
            </div>
            <div>
                <label class="label">Password</label>
                <PasswordInput v-model="form.password" autocomplete="new-password" />
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full transition-all duration-300"
                        :class="{ 'bg-red-500': strengthClass === 'weak', 'bg-amber-500': strengthClass === 'medium', 'bg-emerald-500': strengthClass === 'strong' }"
                        :style="{ width: strengthPercent + '%' }"></div>
                </div>
                <ul class="mt-3 grid gap-1.5 sm:grid-cols-2">
                    <li v-for="rule in rules" :key="rule.label" class="flex items-center gap-2 text-xs" :class="rule.ok ? 'text-emerald-700' : 'text-slate-500'">
                        <CircleCheck v-if="rule.ok" class="h-4 w-4 shrink-0" />
                        <Circle v-else class="h-4 w-4 shrink-0" />
                        {{ rule.label }}
                    </li>
                </ul>
            </div>
            <div>
                <label class="label">Confirm password</label>
                <PasswordInput v-model="form.password_confirmation" name="password_confirmation" autocomplete="new-password" />
                <p v-if="form.password_confirmation" class="mt-1.5 text-xs font-medium" :class="passwordsMatch ? 'text-emerald-700' : 'text-red-600'">
                    {{ passwordsMatch ? 'Passwords match' : 'Passwords do not match' }}
                </p>
            </div>
            <button type="submit" class="btn btn-primary w-full" :disabled="loading || !canSubmit">
                {{ loading ? 'Registering...' : 'Create account' }}
            </button>
            <div v-if="error" class="alert alert-error">{{ error }}</div>
            <p class="text-center text-xs leading-5 text-slate-500">
                By registering you agree to our
                <router-link to="/terms" target="_blank" class="font-medium text-brand-700 hover:text-brand-800">Terms of Service</router-link> and
                <router-link to="/privacy" target="_blank" class="font-medium text-brand-700 hover:text-brand-800">Privacy Policy</router-link>.
            </p>
            <p class="text-center text-sm text-slate-600">
                Already have an account?
                <router-link to="/login" class="font-semibold text-brand-700 hover:text-brand-800">Log in</router-link>
            </p>
        </form>
    </AuthLayout>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import PasswordInput from '../components/PasswordInput.vue';
import AuthLayout from '../components/AuthLayout.vue';
import { siteContent, loadSiteContent } from '../siteContent.js';
import { Circle, CircleCheck, MailCheck } from 'lucide-vue-next';

export default {
    name: 'Register',
    components: { PasswordInput, AuthLayout, Circle, CircleCheck, MailCheck },
    setup() {
        const form = ref({
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
        });
        const loading = ref(false);
        const error = ref('');
        const registered = ref(false);
        const notice = ref('');
        const resending = ref(false);

        const rules = computed(() => {
            const p = form.value.password;
            return [
                { label: 'At least 8 characters', ok: p.length >= 8 },
                { label: 'One lowercase letter', ok: /[a-z]/.test(p) },
                { label: 'One uppercase letter', ok: /[A-Z]/.test(p) },
                { label: 'One number', ok: /\d/.test(p) },
                { label: 'One special character (e.g. !@#$%)', ok: /[^A-Za-z0-9]/.test(p) },
            ];
        });
        const passedCount = computed(() => rules.value.filter(r => r.ok).length);
        const strengthPercent = computed(() => (passedCount.value / rules.value.length) * 100);
        const strengthClass = computed(() => {
            if (passedCount.value <= 2) return 'weak';
            if (passedCount.value < rules.value.length) return 'medium';
            return 'strong';
        });
        const passwordsMatch = computed(() => form.value.password === form.value.password_confirmation);
        const canSubmit = computed(() => passedCount.value === rules.value.length && passwordsMatch.value);

        const handleRegister = async () => {
            loading.value = true;
            error.value = '';

            try {
                const response = await axios.post('/auth/register', form.value);
                notice.value = response.data.email_sent === false ? response.data.message : '';
                registered.value = true;
            } catch (err) {
                if (err.response?.data?.errors) {
                    // Handle Laravel validation errors
                    const errors = err.response.data.errors;
                    const errorMessages = Object.values(errors).flat();
                    error.value = errorMessages.join(', ') || 'Registration failed';
                } else {
                    error.value = err.response?.data?.message || err.message || 'Registration failed';
                }
                console.error('Registration error:', err.response?.data || err);
            } finally {
                loading.value = false;
            }
        };

        const resend = async () => {
            resending.value = true;
            try {
                const response = await axios.post('/auth/email/resend', { email: form.value.email });
                notice.value = response.data.message;
            } catch (err) {
                notice.value = err.response?.data?.message || 'Could not resend the email. Try again shortly.';
            } finally {
                resending.value = false;
            }
        };

        onMounted(() => loadSiteContent().catch(() => {}));
        const registrationClosed = computed(() => siteContent.value?.registration_open === false);

        return {
            registrationClosed,
            form,
            loading,
            error,
            registered,
            notice,
            resending,
            rules,
            strengthPercent,
            strengthClass,
            passwordsMatch,
            canSubmit,
            handleRegister,
            resend,
        };
    },
};
</script>
