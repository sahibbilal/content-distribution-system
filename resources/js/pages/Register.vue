<template>
    <div class="auth-container">
        <div class="auth-card">
            <h1>Register</h1>
            <form @submit.prevent="handleRegister">
                <div class="form-group">
                    <label>Name</label>
                    <input v-model="form.name" type="text" required />
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input v-model="form.email" type="email" required />
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input v-model="form.password" type="password" required />
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input v-model="form.password_confirmation" type="password" required />
                </div>
                <button type="submit" class="btn-primary" :disabled="loading">
                    {{ loading ? 'Registering...' : 'Register' }}
                </button>
                <p class="auth-link">
                    Already have an account? <router-link to="/login">Login</router-link>
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
    name: 'Register',
    setup() {
        const router = useRouter();
        const form = ref({
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
        });
        const loading = ref(false);
        const error = ref('');

        const handleRegister = async () => {
            if (form.value.password !== form.value.password_confirmation) {
                error.value = 'Passwords do not match';
                return;
            }

            loading.value = true;
            error.value = '';

            try {
                const response = await axios.post('/auth/register', form.value);
                localStorage.setItem('token', response.data.token);
                axios.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;
                router.push('/');
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

        return {
            form,
            loading,
            error,
            handleRegister,
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

