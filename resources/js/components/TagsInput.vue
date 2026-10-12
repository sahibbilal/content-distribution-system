<template>
    <div>
        <div class="input flex min-h-[2.5rem] flex-wrap items-center gap-1.5 py-1.5" @click="$refs.field.focus()">
            <span v-for="(tag, index) in modelValue" :key="tag" class="badge badge-neutral gap-1">
                {{ tag }}
                <button type="button" class="text-slate-400 hover:text-slate-700" :aria-label="`Remove ${tag}`" @click.stop="remove(index)">
                    <X class="h-3 w-3" />
                </button>
            </span>
            <input
                ref="field"
                v-model="draft"
                type="text"
                maxlength="50"
                class="min-w-[8rem] flex-1 border-0 bg-transparent p-0 text-sm outline-none focus:ring-0"
                :placeholder="modelValue.length ? '' : placeholder"
                @keydown.enter.prevent="add"
                @keydown.188.prevent="add"
                @keydown.delete="removeLast"
                @blur="add"
            />
        </div>
        <p class="hint">Press Enter or comma to add a tag. Tags are sent to the Website platform.</p>
    </div>
</template>

<script>
import { ref } from 'vue';
import { X } from 'lucide-vue-next';

const MAX_TAGS = 20;

export default {
    name: 'TagsInput',
    components: { X },
    props: {
        modelValue: { type: Array, default: () => [] },
        placeholder: { type: String, default: 'e.g. AI, PHP' },
    },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        const draft = ref('');

        const add = () => {
            // pasting "AI, PHP" adds both
            const fresh = draft.value.split(',').map(t => t.trim()).filter(Boolean);
            draft.value = '';
            const tags = [...props.modelValue];
            for (const tag of fresh) {
                if (tags.length < MAX_TAGS && !tags.some(t => t.toLowerCase() === tag.toLowerCase())) tags.push(tag);
            }
            if (tags.length !== props.modelValue.length) emit('update:modelValue', tags);
        };

        const remove = (index) => emit('update:modelValue', props.modelValue.filter((_, i) => i !== index));

        const removeLast = () => {
            if (draft.value === '' && props.modelValue.length) remove(props.modelValue.length - 1);
        };

        return { draft, add, remove, removeLast };
    },
};
</script>
