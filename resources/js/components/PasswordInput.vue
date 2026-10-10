<template>
    <div class="relative">
        <input
            :type="show ? 'text' : 'password'"
            :value="modelValue"
            :autocomplete="autocomplete"
            :name="name"
            class="input pr-11"
            required
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600"
            :aria-label="show ? 'Hide password' : 'Show password'"
            @click="show = !show"
        >
            <EyeOff v-if="show" class="h-5 w-5" />
            <Eye v-else class="h-5 w-5" />
        </button>
    </div>
</template>

<script>
import { ref } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

export default {
    name: 'PasswordInput',
    components: { Eye, EyeOff },
    props: {
        modelValue: { type: String, default: '' },
        autocomplete: { type: String, default: 'current-password' },
        name: { type: String, default: 'password' },
    },
    emits: ['update:modelValue'],
    setup() {
        return { show: ref(false) };
    },
};
</script>
