<template>
    <div class="admin">
        <h1>AI integrations</h1>
        <AdminNav />

        <p class="lead">
            Connect the AI providers the app can use. API keys are stored encrypted and are never shown again after saving.
            <strong>Anthropic</strong> writes the drafts when someone presses "Ask AI" on Create Post (its Model field is optional; the default is a current Claude model).
            <strong>OpenAI</strong> makes the pictures: set its Model field to the image model (e.g. gpt-image-1).
        </p>

        <div v-if="loading" class="muted">Loading...</div>
        <div v-if="error" class="error-message">{{ error }}</div>

        <section v-for="item in items" :key="item.provider" class="panel">
            <div class="head">
                <h2>{{ item.label }}</h2>
                <span class="badge" :class="item.enabled ? 'ok' : 'off'">{{ item.enabled ? 'Enabled' : 'Disabled' }}</span>
            </div>

            <div class="form-group">
                <label>API key</label>
                <input
                    v-model="drafts[item.provider].api_key"
                    type="password"
                    autocomplete="off"
                    :placeholder="item.has_key ? 'Saved (' + item.key_hint + ') - type to replace' : 'Paste API key'"
                />
            </div>
            <div class="form-group">
                <label>Model <span class="muted">(optional)</span></label>
                <input v-model="drafts[item.provider].model" type="text" :placeholder="modelHint[item.provider]" />
            </div>
            <label class="check">
                <input v-model="drafts[item.provider].enabled" type="checkbox" /> Enabled
            </label>

            <div class="actions">
                <button type="button" class="btn-primary" :disabled="busy === item.provider" @click="save(item)">Save</button>
                <button type="button" class="btn-secondary" :disabled="busy === item.provider || !item.has_key" @click="test(item)">Test connection</button>
                <button v-if="item.has_key" type="button" class="link-btn danger" :disabled="busy === item.provider" @click="removeKey(item)">Remove key</button>
            </div>

            <div v-if="results[item.provider]" class="result" :class="results[item.provider].ok ? 'ok' : 'bad'">
                {{ results[item.provider].text }}
            </div>
        </section>
    </div>
</template>

<script>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import AdminNav from '../components/AdminNav.vue';

export default {
    name: 'AdminAi',
    components: { AdminNav },
    setup() {
        const items = ref([]);
        const drafts = reactive({});
        const results = reactive({});
        const loading = ref(true);
        const busy = ref(null);
        const error = ref('');

        const modelHint = {
            anthropic: 'e.g. claude-haiku-5-5',
            openai: 'e.g. a current OpenAI model',
        };

        const applyItem = (item) => {
            const index = items.value.findIndex(i => i.provider === item.provider);
            if (index === -1) {
                items.value.push(item);
            } else {
                items.value[index] = item;
            }
            drafts[item.provider] = { api_key: '', model: item.model || '', enabled: item.enabled };
        };

        onMounted(async () => {
            try {
                const { data } = await axios.get('/admin/ai');
                data.forEach(applyItem);
            } catch (err) {
                error.value = err.response?.data?.message || 'Could not load AI integrations.';
            } finally {
                loading.value = false;
            }
        });

        const submit = async (item, extra = {}) => {
            busy.value = item.provider;
            results[item.provider] = null;
            try {
                const draft = drafts[item.provider];
                const { data } = await axios.put(`/admin/ai/${item.provider}`, {
                    model: draft.model || null,
                    enabled: draft.enabled,
                    api_key: draft.api_key || null,
                    ...extra,
                });
                applyItem(data);
                results[item.provider] = { ok: true, text: 'Saved.' };
            } catch (err) {
                const errors = err.response?.data?.errors;
                results[item.provider] = {
                    ok: false,
                    text: errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message || 'Save failed.',
                };
            } finally {
                busy.value = null;
            }
        };

        const save = (item) => submit(item);
        const removeKey = (item) => submit(item, { clear_key: true, enabled: false });

        const test = async (item) => {
            busy.value = item.provider;
            results[item.provider] = { ok: true, text: 'Testing...' };
            try {
                const { data } = await axios.post(`/admin/ai/${item.provider}/test`);
                results[item.provider] = { ok: data.success, text: data.message };
            } catch (err) {
                results[item.provider] = { ok: false, text: err.response?.data?.message || 'Test failed.' };
            } finally {
                busy.value = null;
            }
        };

        return { items, drafts, results, loading, busy, error, modelHint, save, removeKey, test };
    },
};
</script>

<style scoped>
.admin { max-width: 760px; }
.admin h1 { margin-bottom: 1rem; }
.lead { color: #666; margin-bottom: 1.5rem; line-height: 1.6; }
.muted { color: #888; font-size: 0.9rem; }
.panel { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 1.5rem; margin-bottom: 1.5rem; }
.head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.head h2 { font-size: 1.25rem; }
.badge { padding: 0.15rem 0.7rem; border-radius: 999px; font-size: 0.8rem; }
.badge.ok { background: #e8f8ee; color: #1e7e43; }
.badge.off { background: #eee; color: #777; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.4rem; color: #555; font-weight: 500; }
.form-group input { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
.check { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; color: #555; }
.actions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
.btn-primary { padding: 0.6rem 1.5rem; background: #667eea; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
.btn-secondary { padding: 0.6rem 1.25rem; background: #fff; color: #667eea; border: 1px solid #667eea; border-radius: 4px; cursor: pointer; }
.btn-primary:disabled, .btn-secondary:disabled { opacity: 0.5; cursor: not-allowed; }
.link-btn { background: none; border: none; cursor: pointer; padding: 0 0.4rem; }
.link-btn.danger { color: #e74c3c; }
.result { margin-top: 1rem; padding: 0.75rem; border-radius: 4px; }
.result.ok { background: #e8f8ee; color: #1e7e43; }
.result.bad { background: #fee; color: #c33; }
.error-message { margin-bottom: 1rem; padding: 0.75rem; background: #fee; color: #c33; border-radius: 4px; }
</style>
