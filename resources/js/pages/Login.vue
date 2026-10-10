<template>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo"><BrandLogo :size="72" /></div>
            <h1>Login</h1>
            <div v-if="info" class="notice-message">{{ info }}</div>
            <form @submit.prevent="handleLogin">
                <div class="form-group">
                    <label>Email</label>
                    <input v-model="form.email" type="email" name="email" autocomplete="username" required />
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <PasswordInput v-model="form.password" autocomplete="current-password" />
                </div>
                <label class="remember">
                    <input v-model="form.remember" type="checkbox" /> Remember me
                </label>
                <button type="submit" class="btn-primary" :disabled="loading">
                    {{ loading ? 'Logging in...' : 'Login' }}
                </button>
                <p class="auth-link">
                    Don't have an account? <router-link to="/register">Register</router-link>
                </p>
                <div v-if="error" class="error-message">{{ error }}</div>
                <p v-if="needsVerification" class="auth-link">
                    <a href="#" @click.prevent="resend">Resend verification email</a>
                </p>
            </form>
        </div>
    </div>
</template>

<script>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import PasswordInput from '../components/PasswordInput.vue';
import BrandLogo from '../components/BrandLogo.vue';

export default {
    name: 'Login',
    components: { PasswordInput, BrandLogo },
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

<style scoped>
.auth-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.auth-card {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    width: 100%;
    max-width: 400px;
}

.auth-logo {
    text-align: center;
    margin-bottom: 1.25rem;
}

.auth-card h1 {
    margin-bottom: 1.5rem;
    text-align: center;
    color: #333;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #555;
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.btn-primary {
    width: 100%;
    padding: 0.75rem;
    background-color: #667eea;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-primary:hover:not(:disabled) {
    background-color: #5568d3;
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.auth-link {
    text-align: center;
    margin-top: 1rem;
    color: #666;
}

.auth-link a {
    color: #667eea;
    text-decoration: none;
}

.notice-message {
    margin-bottom: 1rem;
    padding: 0.75rem;
    background-color: #eef3ff;
    color: #3b4cb8;
    border-radius: 4px;
    text-align: center;
}

.remember {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    color: #555;
    cursor: pointer;
}

.error-message {
    margin-top: 1rem;
    padding: 0.75rem;
    background-color: #fee;
    color: #c33;
    border-radius: 4px;
    text-align: center;
}
</style>

