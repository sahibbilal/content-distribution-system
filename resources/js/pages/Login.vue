<template>
    <div class="auth-container">
        <div class="auth-card">
            <h1>Login</h1>
            <form @submit.prevent="handleLogin">
                <div class="form-group">
                    <label>Email</label>
                    <input v-model="form.email" type="email" required />
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input v-model="form.password" type="password" required />
                </div>
                <button type="submit" class="btn-primary" :disabled="loading">
                    {{ loading ? 'Logging in...' : 'Login' }}
                </button>
                <p class="auth-link">
                    Don't have an account? <router-link to="/register">Register</router-link>
                </p>
                <div v-if="error" class="error-message">{{ error }}</div>
            </form>
        </div>
    </div>
</template>

<script>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

export default {
    name: 'Login',
    setup() {
        const router = useRouter();
        const form = ref({ email: '', password: '' });
        const loading = ref(false);
        const error = ref('');

        const handleLogin = async () => {
            loading.value = true;
            error.value = '';

            try {
                const response = await axios.post('/auth/login', form.value);
                localStorage.setItem('token', response.data.token);
                axios.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;
                router.push('/');
            } catch (err) {
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
            handleLogin,
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
</style>

