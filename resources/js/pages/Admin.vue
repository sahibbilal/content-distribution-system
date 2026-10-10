<template>
    <div class="admin">
        <h1>Site content</h1>
        <AdminNav />
        <p class="lead">Edit what visitors see on the home, terms and privacy pages. Changes go live when you save.</p>

        <div v-if="loading" class="muted">Loading...</div>

        <form v-else-if="form" @submit.prevent="save">
            <section class="panel">
                <h2>General</h2>
                <div class="form-group">
                    <label>Contact email (home, terms and privacy pages)</label>
                    <input v-model="form.contact_email" type="email" required />
                </div>
                <div class="form-group">
                    <label>Hero title</label>
                    <input v-model="form.hero.title" type="text" required />
                </div>
                <div class="form-group">
                    <label>Hero text</label>
                    <textarea v-model="form.hero.subtitle" rows="3" required></textarea>
                </div>
            </section>

            <section class="panel">
                <h2>What you can do</h2>
                <div v-for="(f, i) in form.features" :key="i" class="row-card">
                    <div class="row-inline">
                        <input v-model="f.icon" type="text" class="icon-input" placeholder="🔗" maxlength="8" />
                        <input v-model="f.title" type="text" placeholder="Title" />
                        <button type="button" class="btn-remove" @click="remove(form.features, i)">Remove</button>
                    </div>
                    <textarea v-model="f.text" rows="2" placeholder="Description"></textarea>
                </div>
                <button type="button" class="btn-add" @click="form.features.push({ icon: '', title: '', text: '' })">+ Add item</button>
            </section>

            <section class="panel">
                <h2>Supported platforms</h2>
                <div v-for="(p, i) in form.platforms" :key="i" class="row-inline">
                    <input v-model="form.platforms[i]" type="text" placeholder="Platform name" />
                    <button type="button" class="btn-remove" @click="remove(form.platforms, i)">Remove</button>
                </div>
                <button type="button" class="btn-add" @click="form.platforms.push('')">+ Add platform</button>
            </section>

            <section class="panel">
                <h2>Pricing plans</h2>
                <div v-for="(plan, i) in form.plans" :key="i" class="row-card">
                    <div class="row-inline">
                        <input v-model="plan.name" type="text" placeholder="Plan name" />
                        <input v-model="plan.price" type="text" class="price-input" placeholder="500" />
                        <button type="button" class="btn-remove" @click="remove(form.plans, i)">Remove</button>
                    </div>
                    <label class="check"><input v-model="plan.featured" type="checkbox" /> Highlight this plan</label>
                    <label class="small-label">Features (one per line)</label>
                    <textarea v-model="plan.featuresText" rows="4"></textarea>
                </div>
                <button type="button" class="btn-add" @click="form.plans.push({ name: '', price: '', featured: false, featuresText: '' })">+ Add plan</button>
                <p class="muted">Price is shown as "Rs {price} / month".</p>
            </section>

            <section class="panel">
                <h2>Payment methods</h2>
                <p class="muted">Shown under "How to join" on the pricing section. Leave empty to show "contact us" instead.</p>
                <div v-for="(m, i) in form.payment_methods" :key="i" class="row-card">
                    <div class="row-inline">
                        <input v-model="m.name" type="text" placeholder="Method (e.g. JazzCash, Bank transfer)" />
                        <button type="button" class="btn-remove" @click="remove(form.payment_methods, i)">Remove</button>
                    </div>
                    <div v-for="(d, j) in m.details" :key="j" class="row-inline">
                        <input v-model="d.label" type="text" placeholder="Label (e.g. Account title)" />
                        <input v-model="d.value" type="text" placeholder="Value" />
                        <button type="button" class="btn-remove" @click="remove(m.details, j)">×</button>
                    </div>
                    <button type="button" class="btn-add" @click="m.details.push({ label: '', value: '' })">+ Add detail</button>
                </div>
                <button type="button" class="btn-add" @click="form.payment_methods.push({ name: '', details: [] })">+ Add payment method</button>
            </section>

            <div v-if="error" class="error-message">{{ error }}</div>
            <div v-if="success" class="success-message">{{ success }}</div>
            <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save changes' }}</button>
        </form>

        <div v-else class="error-message">{{ error || 'Could not load content.' }}</div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { loadSiteContent, setSiteContent } from '../siteContent.js';
import AdminNav from '../components/AdminNav.vue';

const toForm = (c) => ({
    contact_email: c.contact_email,
    hero: { ...c.hero },
    features: c.features.map(f => ({ icon: f.icon || '', title: f.title, text: f.text })),
    platforms: [...c.platforms],
    plans: c.plans.map(p => ({
        name: p.name,
        price: p.price,
        featured: !!p.featured,
        featuresText: p.features.join('\n'),
    })),
    payment_methods: c.payment_methods.map(m => ({ name: m.name, details: m.details.map(d => ({ ...d })) })),
});

const toPayload = (f) => ({
    contact_email: f.contact_email.trim(),
    hero: { title: f.hero.title.trim(), subtitle: f.hero.subtitle.trim() },
    features: f.features
        .filter(x => x.title.trim())
        .map(x => ({ icon: x.icon.trim(), title: x.title.trim(), text: x.text.trim() })),
    platforms: f.platforms.map(p => p.trim()).filter(Boolean),
    plans: f.plans
        .filter(p => p.name.trim())
        .map(p => ({
            name: p.name.trim(),
            price: p.price.trim(),
            featured: p.featured,
            features: p.featuresText.split('\n').map(s => s.trim()).filter(Boolean),
        })),
    payment_methods: f.payment_methods
        .filter(m => m.name.trim())
        .map(m => ({
            name: m.name.trim(),
            details: m.details
                .filter(d => d.label.trim() && d.value.trim())
                .map(d => ({ label: d.label.trim(), value: d.value.trim() })),
        })),
});

export default {
    name: 'Admin',
    components: { AdminNav },
    setup() {
        const form = ref(null);
        const loading = ref(true);
        const saving = ref(false);
        const error = ref('');
        const success = ref('');

        const remove = (list, index) => list.splice(index, 1);

        onMounted(async () => {
            try {
                form.value = toForm(await loadSiteContent(true));
            } catch (err) {
                error.value = err.response?.data?.message || 'Could not load content.';
            } finally {
                loading.value = false;
            }
        });

        const save = async () => {
            saving.value = true;
            error.value = '';
            success.value = '';

            try {
                const response = await axios.put('/admin/site-content', toPayload(form.value));
                setSiteContent(response.data);
                form.value = toForm(response.data);
                success.value = 'Saved. The public pages now show this content.';
            } catch (err) {
                const errors = err.response?.data?.errors;
                error.value = errors
                    ? Object.values(errors).flat().join(' ')
                    : err.response?.data?.message || 'Save failed';
            } finally {
                saving.value = false;
            }
        };

        return { form, loading, saving, error, success, remove, save };
    },
};
</script>

<style scoped>
.admin { max-width: 860px; }
.admin h1 { margin-bottom: 0.25rem; }
.lead { color: #666; margin-bottom: 1.5rem; }
.muted { color: #888; font-size: 0.9rem; margin: 0.5rem 0; }
.panel { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 1.5rem; margin-bottom: 1.5rem; }
.panel h2 { margin-bottom: 1rem; font-size: 1.25rem; }
.form-group { margin-bottom: 1rem; }
.form-group label, .small-label { display: block; margin-bottom: 0.4rem; color: #555; font-weight: 500; }
input[type="text"], input[type="email"], textarea {
    width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; font-family: inherit;
}
.row-card { border: 1px solid #eee; border-radius: 6px; padding: 0.75rem; margin-bottom: 0.75rem; background: #fafafa; }
.row-inline { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center; }
.row-inline input { flex: 1; min-width: 0; }
input.icon-input { width: 4.5rem; flex: none; text-align: center; }
input.price-input { width: 8rem; flex: none; }
.check { display: flex; align-items: center; gap: 0.5rem; margin: 0.25rem 0 0.75rem; color: #555; }
.btn-add { background: none; border: 1px dashed #667eea; color: #667eea; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; margin-top: 0.25rem; }
.btn-remove { background: none; border: none; color: #e74c3c; cursor: pointer; flex: none; padding: 0.25rem 0.5rem; }
.btn-primary { padding: 0.75rem 2rem; background: #667eea; color: #fff; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.error-message { margin-bottom: 1rem; padding: 0.75rem; background: #fee; color: #c33; border-radius: 4px; }
.success-message { margin-bottom: 1rem; padding: 0.75rem; background: #e8f8ee; color: #1e7e43; border-radius: 4px; }
</style>
