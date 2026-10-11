<template>
    <div class="rte rounded-lg bg-white ring-1 ring-inset ring-slate-300 focus-within:ring-2 focus-within:ring-brand-600">
        <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b border-slate-200 p-1.5" role="toolbar" aria-label="Text formatting">
            <button v-for="b in buttons" :key="b.label" type="button" class="rte-btn" :class="{ 'is-active': b.active() }"
                :title="b.label" :aria-label="b.label" :aria-pressed="b.active()" @click="b.run">
                <component :is="b.icon" class="h-4 w-4" />
            </button>
            <span class="mx-1 h-5 w-px bg-slate-200"></span>
            <button type="button" class="rte-btn" title="Undo" aria-label="Undo" :disabled="!editor.can().undo()" @click="editor.chain().focus().undo().run()">
                <Undo2 class="h-4 w-4" />
            </button>
            <button type="button" class="rte-btn" title="Redo" aria-label="Redo" :disabled="!editor.can().redo()" @click="editor.chain().focus().redo().run()">
                <Redo2 class="h-4 w-4" />
            </button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<script>
import { watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extensions';
import { Bold, Heading2, Italic, Link2, List, ListOrdered, Quote, Redo2, Strikethrough, Underline, Undo2 } from 'lucide-vue-next';

export default {
    name: 'RichTextEditor',
    components: { EditorContent, Undo2, Redo2 },
    props: {
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: '' },
    },
    // update:modelValue = cleaned HTML ('' when empty); update:text = the plain text platforms will receive
    emits: ['update:modelValue', 'update:text'],
    setup(props, { emit }) {
        const emitChange = (editor) => {
            const text = editor.getText({ blockSeparator: '\n\n' }).trim();
            emit('update:modelValue', text ? editor.getHTML() : '');
            emit('update:text', text);
        };

        const editor = useEditor({
            content: props.modelValue,
            extensions: [StarterKit.configure({
                heading: { levels: [2] },
                code: false,
                codeBlock: false,
                horizontalRule: false,
                link: { openOnClick: false, autolink: true },
            }), Placeholder.configure({ placeholder: props.placeholder })],
            editorProps: {
                attributes: {
                    class: 'rte-content min-h-52 max-h-[32rem] overflow-y-auto px-3.5 py-3 text-sm text-slate-900 focus:outline-none',
                },
            },
            onCreate: ({ editor }) => emitChange(editor),
            onUpdate: ({ editor }) => emitChange(editor),
        });

        // Parent replaced the content (e.g. "Use as new post" finished loading)
        watch(() => props.modelValue, (html) => {
            if (editor.value && html !== (editor.value.getText().trim() ? editor.value.getHTML() : '')) {
                editor.value.commands.setContent(html || '', { emitUpdate: false });
                emitChange(editor.value);
            }
        });

        const setLink = () => {
            const previous = editor.value.getAttributes('link').href || '';
            const url = window.prompt('Link address (https://...)', previous);
            if (url === null) return;
            const chain = editor.value.chain().focus().extendMarkRange('link');
            if (url.trim() === '') chain.unsetLink().run();
            else chain.setLink({ href: url.trim() }).run();
        };

        const toggle = (name, fn) => ({ active: () => !!editor.value?.isActive(name), run: () => fn(editor.value.chain().focus()).run() });
        const buttons = [
            { label: 'Bold', icon: Bold, ...toggle('bold', c => c.toggleBold()) },
            { label: 'Italic', icon: Italic, ...toggle('italic', c => c.toggleItalic()) },
            { label: 'Underline', icon: Underline, ...toggle('underline', c => c.toggleUnderline()) },
            { label: 'Strikethrough', icon: Strikethrough, ...toggle('strike', c => c.toggleStrike()) },
            { label: 'Heading', icon: Heading2, ...toggle('heading', c => c.toggleHeading({ level: 2 })) },
            { label: 'Bulleted list', icon: List, ...toggle('bulletList', c => c.toggleBulletList()) },
            { label: 'Numbered list', icon: ListOrdered, ...toggle('orderedList', c => c.toggleOrderedList()) },
            { label: 'Quote', icon: Quote, ...toggle('blockquote', c => c.toggleBlockquote()) },
            { label: 'Link', icon: Link2, active: () => !!editor.value?.isActive('link'), run: setLink },
        ];

        onBeforeUnmount(() => editor.value?.destroy());

        return { editor, buttons };
    },
};
</script>

<style scoped>
.rte-btn {
    display: inline-flex;
    height: 2rem;
    width: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    color: #475569;
    transition: background-color 0.15s;
}
.rte-btn:hover:not(:disabled) { background-color: #f1f5f9; }
.rte-btn:disabled { opacity: 0.35; cursor: default; }
.rte-btn.is-active { background-color: #e0e7ff; color: #3730a3; }

/* Tailwind's reset strips list/heading styles, so restore them inside the editor */
.rte :deep(.rte-content > * + *) { margin-top: 0.75em; }
.rte :deep(.rte-content ul) { list-style: disc; padding-left: 1.4em; }
.rte :deep(.rte-content ol) { list-style: decimal; padding-left: 1.4em; }
.rte :deep(.rte-content h2) { font-size: 1.15rem; font-weight: 700; }
.rte :deep(.rte-content blockquote) { border-left: 3px solid #cbd5e1; padding-left: 0.9em; color: #475569; }
.rte :deep(.rte-content a) { color: #4338ca; text-decoration: underline; }
.rte :deep(.rte-content p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    color: #94a3b8;
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
