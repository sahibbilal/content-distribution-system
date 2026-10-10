<template>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo"><BrandLogo :size="72" /></div>
            <template v-if="registered">
                <h1>Check your email</h1>
                <p class="info-text">
                    We sent a verification link to <strong>{{ form.email }}</strong>.
                    Click it to verify your address, then log in.
                </p>
                <div v-if="notice" class="notice-message">{{ notice }}</div>
                <button type="button" class="btn-primary" :disabled="resending" @click="resend">
                    {{ resending ? 'Sending...' : 'Resend verification email' }}
                </button>
                <p class="auth-link"><router-link to="/login">Go to login</router-link></p>
            </template>

            <template v-else>
                <h1>Register</h1>
                <form @submit.prevent="handleRegister">
                    <div class="form-group">
                        <label>Name</label>
                        <input v-model="form.name" type="text" autocomplete="name" required />
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input v-model="form.email" type="email" autocomplete="email" required />
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <PasswordInput v-model="form.password" autocomplete="new-password" />
                        <div class="strength">
                            <div class="strength-bar" :class="strengthClass" :style="{ width: strengthPercent + '%' }"></div>
                        </div>
                        <ul class="rules">
                            <li v-for="rule in rules" :key="rule.label" :class="{ ok: rule.ok }">
                                <span class="rule-icon">{{ rule.ok ? '✓' : '○' }}</span> {{ rule.label }}
                            </li>
                        </ul>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <PasswordInput v-model="form.password_confirmation" name="password_confirmation" autocomplete="new-password" />
                        <p v-if="form.password_confirmation" class="match" :class="{ ok: passwordsMatch }">
                            {{ passwordsMatch ? '✓ Passwords match' : '✗ Passwords do not match' }}
                        </p>
                    </div>
                    <button type="submit" class="btn-primary" :disabled="loading || !canSubmit">
                        {{ loading ? 'Registering...' : 'Register' }}
                    </button>
                    <p class="legal-note">
                        By registering you agree to our
                        <router-link to="/terms" target="_blank">Terms of Service</router-link> and
                        <router-link to="/privacy" target="_blank">Privacy Policy</router-link>.
                    </p>
                    <p class="auth-link">
                        Already have an account? <router-link to="/login">Login</router-link>
                    </p>
                    <div v-if="error" class="error-message">{{ error }}</div>
                </form>
            </template>
        </div>
    </div>
</template>

<script>
import { ref, computed } from 'vue';
import axios from 'axios';
import PasswordInput from '../components/PasswordInput.vue';
import BrandLogo from '../components/BrandLogo.vue';

export default {
    name: 'Register',
    components: { PasswordInput, BrandLogo },
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

        return {
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

.error-message {
    margin-top: 1rem;
    padding: 0.75rem;
    background-color: #fee;
    color: #c33;
    border-radius: 4px;
    text-align: center;
}

.notice-message {
    margin-bottom: 1rem;
    padding: 0.75rem;
    background-color: #eef3ff;
    color: #3b4cb8;
    border-radius: 4px;
    text-align: center;
}

.info-text {
    margin-bottom: 1.25rem;
    color: #555;
    line-height: 1.5;
    text-align: center;
}

.strength {
    height: 6px;
    margin-top: 0.5rem;
    background: #eee;
    border-radius: 3px;
    overflow: hidden;
}

.strength-bar {
    height: 100%;
    transition: width 0.2s, background-color 0.2s;
}

.strength-bar.weak { background: #e74c3c; }
.strength-bar.medium { background: #f39c12; }
.strength-bar.strong { background: #27ae60; }

.rules {
    list-style: none;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: #888;
}

.rules li.ok {
    color: #27ae60;
}

.rule-icon {
    display: inline-block;
    width: 1.1em;
}

.legal-note {
    margin-top: 1rem;
    font-size: 0.8rem;
    color: #777;
    text-align: center;
}

.legal-note a {
    color: #667eea;
}

.match {
    margin-top: 0.4rem;
    font-size: 0.875rem;
    color: #c0392b;
}

.match.ok {
    color: #27ae60;
}
</style>
