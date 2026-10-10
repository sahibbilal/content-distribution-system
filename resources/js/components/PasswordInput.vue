<template>
    <div class="pw">
        <input
            :type="show ? 'text' : 'password'"
            :value="modelValue"
            :autocomplete="autocomplete"
            :name="name"
            required
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <button
            type="button"
            class="pw-toggle"
            :aria-label="show ? 'Hide password' : 'Show password'"
            @click="show = !show"
        >{{ show ? '🙈' : '👁️' }}</button>
    </div>
</template>

<script>
import { ref } from 'vue';

export default {
    name: 'PasswordInput',
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

<style scoped>
.pw {
    position: relative;
}

.pw input {
    width: 100%;
    padding: 0.75rem 2.75rem 0.75rem 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.pw-toggle {
    position: absolute;
    top: 50%;
    right: 0.5rem;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.1rem;
    padding: 0.25rem;
}
</style>
