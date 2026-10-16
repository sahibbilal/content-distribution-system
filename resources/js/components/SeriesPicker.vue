<template>
    <div>
        <div class="flex flex-wrap items-center gap-2">
            <select class="input sm:max-w-xs" :value="modelValue ?? ''" :aria-label="label" @change="$emit('update:modelValue', $event.target.value ? Number($event.target.value) : null)">
                <option value="">No series</option>
                <option v-for="s in series" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <button v-if="!adding" type="button" class="btn btn-secondary btn-sm" @click="adding = true"><Plus class="h-4 w-4" /> New series</button>
        </div>
        <form v-if="adding" class="mt-2 flex flex-wrap items-center gap-2" @submit.prevent="create">
            <input v-model="name" class="input sm:max-w-xs" maxlength="100" placeholder="e.g. GKMBA series" aria-label="New series name" />
            <button type="submit" class="btn btn-primary btn-sm" :disabled="busy || !name.trim()">Add</button>
            <button type="button" class="btn btn-ghost btn-sm" @click="adding = false; name = ''">Cancel</button>
        </form>
        <p v-if="error" class="mt-1.5 text-xs text-red-700">{{ error }}</p>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { Plus } from 'lucide-vue-next';

// Choose the series a post belongs to, or add a new one on the spot
export default {
    name: 'SeriesPicker',
    components: { Plus },
    props: { modelValue: { type: Number, default: null }, label: { type: String, default: 'Series' } },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        const series = ref([]);
        const adding = ref(false);
        const name = ref('');
        const busy = ref(false);
        const error = ref('');

        onMounted(async () => {
            try {
                series.value = (await axios.get('/post-series')).data;
            } catch (e) {
                // posts can still be made without a series
            }
        });

        const create = async () => {
            busy.value = true;
            error.value = '';
            try {
                const { data } = await axios.post('/post-series', { name: name.value });
                series.value.push(data);
                emit('update:modelValue', data.id);
                adding.value = false;
                name.value = '';
            } catch (err) {
                error.value = err.response?.data?.errors?.name?.[0] || err.response?.data?.message || 'Could not add the series.';
            } finally {
                busy.value = false;
            }
        };

        return { series, adding, name, busy, error, create };
    },
};
</script>
