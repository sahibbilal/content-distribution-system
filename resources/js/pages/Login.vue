<template>
    <AuthLayout title="Welcome back" subtitle="Log in to manage and publish your content.">
        <div v-if="info" class="alert alert-info mb-5">{{ info }}</div>
        <form class="space-y-5" @submit.prevent="handleLogin">
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" v-model="form.email" type="email" name="email" autocomplete="username" class="input" placeholder="you@example.com" required />
            </div>
            <div>
                <label class="label">Password</label>
                <PasswordInput v-model="form.password" autocomplete="current-password" />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-600" />
                Remember me
            </label>
            <button type="submit" class="btn btn-primary w-full" :disabled="loading">
                {{ loading ? 'Logging in...' : 'Log in' }}
            </button>
            <div v-if="error" class="alert alert-error">{{ error }}</div>
            <p v-if="needsVerification" class="text-center text-sm">
                <a href="#" class="font-medium text-brand-700 hover:text-brand-800" @click.prevent="resend">Resend verification email</a>
            </p>
            <p class="text-center text-sm text-slate-600">
                Don't have an account?
                <router-link to="/register" class="font-semibold text-brand-700 hover:text-brand-800">Register</router-link>
            </p>
        </form>
    </AuthLayout>
</template>

<script>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import PasswordInput from '../components/PasswordInput.vue';
import AuthLayout from '../components/AuthLayout.vue';

export default {
    name: 'Login',
    components: { PasswordInput, AuthLayout },
    setup() {
        const router = useRouter();
        const route = useRoute();
        const rememberedEmail = localStorage.getItem('remembered_email') || '';
        const form = ref({ email: rememberedEmail, password: '', remember: !!rememberedEmail });
        const loading = ref(false);
        const error = ref('');
        const info = ref(
            route.query.verified ? 'Email verified. You can now log in.'
            : route.query.verify_error ? 'That verification link is invalid or has expired. Log in to request a new one.'
            : ''
        );
        const needsVerification = ref(false);

        const resend = async () => {
            try {
                const response = await axios.post('/auth/email/resend', { email: form.value.email });
                info.value = response.data.message;
                error.value = '';
            } catch (err) {
                error.value = err.response?.data?.message || 'Could not resend the email. Try again shortly.';
            }
        };

        const handleLogin = async () => {
            loading.value = true;
            error.value = '';
            needsVerification.value = false;

            try {
                const response = await axios.post('/auth/login', form.value);
                localStorage.setItem('token', response.data.token);
                localStorage.setItem('user', JSON.stringify(response.data.user));
                sessionStorage.setItem('session_active', '1');
                if (form.value.remember) {
                    localStorage.setItem('remember', '1');
                    localStorage.setItem('remembered_email', form.value.email);
                } else {
                    localStorage.removeItem('remember');
                    localStorage.removeItem('remembered_email');
                }
                axios.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;
                router.push('/dashboard');
            } catch (err) {
                needsVerification.value = !!err.response?.data?.needs_verification;
                if (err.response?.data?.errors) {
                    const errors = err.response.data.errors;
                    const errorMessages = Object.values(errors).flat();
                    error.value = errorMessages.join(', ') || 'Login failed';
                } else {
                    error.value = err.response?.data?.message || err.message || 'Login failed';
                }
                console.error('Login error:', err.response?.data || err);
            } finally {
                loading.value = false;
            }
        };

        return {
            form,
            loading,
            error,
            info,
            needsVerification,
            handleLogin,
            resend,
        };
    },
};
</script>
