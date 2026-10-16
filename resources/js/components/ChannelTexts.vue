<template>
    <div v-if="platforms.length" class="space-y-2">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ mode === 'text' ? 'Text for each channel' : 'Hashtags for each channel' }}</p>

        <div v-for="p in platforms" :key="p.id" class="rounded-lg bg-slate-50 p-3 ring-1 ring-slate-900/5" data-channel>
            <div class="flex flex-wrap items-center gap-3">
                <PlatformIcon :type="p.platform_type" :size="18" />
                <span class="text-sm font-medium text-slate-800">{{ platformLabel(p.platform_type) }}</span>

                <span v-if="limit(p)" class="ml-auto text-xs font-medium tabular-nums" :class="over(p) ? 'text-red-700' : 'text-slate-500'" data-counter>
                    {{ used(p) }} / {{ limit(p) }}
                </span>

                <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-600" :class="limit(p) ? '' : 'ml-auto'">
                    <input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300" :checked="has(p)" @change="toggle(p, $event.target.checked)" />
                    {{ mode === 'text' ? 'Different text' : 'Different hashtags' }}
                </label>
            </div>

            <template v-if="has(p)">
                <textarea v-if="mode === 'text'" :value="overrides[p.id].content" dir="auto" rows="4" class="input mt-3 text-sm"
                    :placeholder="`Text for ${platformLabel(p.platform_type)} only`" @input="set(p, { content: $event.target.value })"></textarea>
                <div v-else class="mt-3">
                    <TagsInput :model-value="overrides[p.id].hashtags" hashtag :hint="''" placeholder="#hashtag, leave empty for none" @update:model-value="set(p, { hashtags: $event })" />
                </div>
            </template>
            <p v-if="over(p)" class="mt-2 text-xs text-red-700">
                {{ platformLabel(p.platform_type) }} allows {{ limit(p) }} characters including spaces and hashtags. Shorten the text, drop hashtags, or write a different text for it.
            </p>
        </div>
    </div>
</template>

<script>
import PlatformIcon from './PlatformIcon.vue';
import TagsInput from './TagsInput.vue';
import { platformLabel } from '../platforms.js';
import { forPlatform, length, limitFor } from '../postText.js';

// One row per selected channel: its character count, and the choice to give it its own text or its own hashtags.
// `overrides` is {platformId: {content?, hashtags?}}; a channel without an entry uses the shared text and hashtags.
export default {
    name: 'ChannelTexts',
    components: { PlatformIcon, TagsInput },
    props: {
        mode: { type: String, required: true }, // 'text' | 'hashtags'
        platforms: { type: Array, required: true }, // the selected platforms
        content: { type: String, default: '' },
        hashtags: { type: Array, default: () => [] },
        overrides: { type: Object, required: true },
    },
    emits: ['update:overrides'],
    setup(props, { emit }) {
        const key = (p) => (props.mode === 'text' ? 'content' : 'hashtags');
        const has = (p) => props.overrides[p.id]?.[key(p)] !== undefined;
        const limit = (p) => limitFor(p);
        const used = (p) => length(forPlatform(props.content, props.hashtags, props.overrides, p.id));
        const over = (p) => limit(p) && used(p) > limit(p);

        const write = (p, patch) => {
            const next = { ...props.overrides, [p.id]: { ...(props.overrides[p.id] || {}), ...patch } };
            emit('update:overrides', next);
        };

        const toggle = (p, on) => {
            if (on) {
                // start from the shared value so the channel only changes what you edit
                write(p, props.mode === 'text' ? { content: props.content } : { hashtags: [...props.hashtags] });
                return;
            }
            const entry = { ...(props.overrides[p.id] || {}) };
            delete entry[key(p)];
            const next = { ...props.overrides };
            if (Object.keys(entry).length) next[p.id] = entry;
            else delete next[p.id];
            emit('update:overrides', next);
        };

        return { platformLabel, has, limit, used, over, set: write, toggle };
    },
};
</script>
