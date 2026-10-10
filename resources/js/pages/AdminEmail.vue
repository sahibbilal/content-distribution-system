<template>
    <div class="admin">
        <h1>Email</h1>
        <AdminNav />

        <div v-if="loading" class="muted">Loading...</div>
        <div v-if="error" class="error-message">{{ error }}</div>

        <template v-if="config">
            <div v-if="!config.sends_real_email" class="error-message">
                The mailer is set to <strong>{{ config.mailer }}</strong>, so emails (including verification links)
                are written to a log file and <strong>not sent</strong>. On this host, set <code>MAIL_MAILER=smtp</code>
                with <code>MAIL_URL="smtp://127.0.0.1:25?verify_peer=0"</code> in the server's <code>.env.production</code>;
                the server's own mail system accepts it without a login.
            </div>

            <section class="panel">
                <h2>Current settings</h2>
                <table>
                    <tr><th>Mailer</th><td>{{ config.mailer }}</td></tr>
                    <tr><th>SMTP host</th><td>{{ config.host }}:{{ config.port }} {{ config.encryption ? '(' + config.encryption + ')' : '' }}</td></tr>
                    <tr><th>SMTP user</th><td>{{ config.username || 'none' }}</td></tr>
                    <tr><th>From</th><td>{{ config.from_name }} &lt;{{ config.from_address }}&gt;</td></tr>
                    <tr><th>Site URL</th><td>{{ config.app_url }} <span class="muted">(used in verification links)</span></td></tr>
                </table>
                <p class="muted">Passwords are never shown. These are changed in the server's <code>.env.production</code>.</p>
            </section>

            <section class="panel">
                <h2>Send a test email</h2>
                <form class="row" @submit.prevent="sendTest">
                    <input v-model="email" type="email" placeholder="you@example.com" required />
                    <button type="submit" class="btn-primary" :disabled="sending">{{ sending ? 'Sending...' : 'Send test' }}</button>
                </form>
                <div v-if="result" class="result" :class="result.ok ? 'ok' : 'bad'">{{ result.text }}</div>
                <p class="muted">
                    "Accepted" means the mail server took the message. If it is not in the inbox or spam, the sender
                    domain's SPF, DKIM and DMARC DNS records are the usual cause.
                </p>
            </section>
        </template>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import AdminNav from '../components/AdminNav.vue';

export default {
    name: 'AdminEmail',
    components: { AdminNav },
    setup() {
        const config = ref(null);
        const email = ref('');
        const loading = ref(true);
        const sending = ref(false);
        const error = ref('');
        const result = ref(null);

        onMounted(async () => {
            try {
                config.value = (await axios.get('/admin/mail')).data;
            } catch (err) {
                error.value = err.response?.data?.message || 'Could not load email settings.';
            } finally {
                loading.value = false;
            }
        });

        const sendTest = async () => {
            sending.value = true;
            result.value = null;
            try {
                const { data } = await axios.post('/admin/mail/test', { email: email.value });
                result.value = { ok: data.success, text: data.message };
            } catch (err) {
                const errors = err.response?.data?.errors;
                result.value = {
                    ok: false,
                    text: errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message || 'Test failed.',
                };
            } finally {
                sending.value = false;
            }
        };

        return { config, email, loading, sending, error, result, sendTest };
    },
};
</script>

<style scoped>
.admin { max-width: 760px; }
.admin h1 { margin-bottom: 1rem; }
.muted { color: #888; font-size: 0.9rem; margin-top: 0.75rem; }
.panel { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 1.5rem; margin-bottom: 1.5rem; }
.panel h2 { font-size: 1.25rem; margin-bottom: 1rem; }
table { width: 100%; border-collapse: collapse; }
th { text-align: left; width: 8rem; padding: 0.4rem 0; color: #666; font-weight: 500; vertical-align: top; }
td { padding: 0.4rem 0; word-break: break-word; }
.row { display: flex; gap: 0.5rem; }
.row input { flex: 1; padding: 0.6rem 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
.btn-primary { padding: 0.6rem 1.5rem; background: #667eea; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.result { margin-top: 1rem; padding: 0.75rem; border-radius: 4px; word-break: break-word; }
.result.ok { background: #e8f8ee; color: #1e7e43; }
.result.bad { background: #fee; color: #c33; }
.error-message { margin-bottom: 1rem; padding: 0.75rem; background: #fee; color: #c33; border-radius: 4px; line-height: 1.5; }
code { background: #f0f0f0; padding: 0.1rem 0.35rem; border-radius: 3px; }
</style>
