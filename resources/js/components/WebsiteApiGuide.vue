<template>
    <div class="space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
        <div>
            <h3 class="font-semibold text-slate-900">What your website needs to provide</h3>
            <p class="mt-1">
                Build these 3 endpoints on your site, then enter the <strong>API URL</strong> (the base, e.g.
                <code>https://yoursite.com/api</code>) and a <strong>bearer token</strong> your site accepts.
                Every request sends <code>Authorization: Bearer &lt;token&gt;</code> and <code>Accept: application/json</code>.
                Return <code>401</code> for a wrong token, and on errors return JSON with a <code>message</code> field (it is shown here).
            </p>
        </div>

        <div v-for="endpoint in endpoints" :key="endpoint.title" class="space-y-1.5">
            <h4 class="font-semibold text-slate-900">
                <span class="mr-1 rounded bg-slate-900 px-1.5 py-0.5 font-mono text-xs text-white">{{ endpoint.method }}</span>
                <code>{{ endpoint.path }}</code>
                <span class="font-normal text-slate-500"> · {{ endpoint.title }}</span>
            </h4>
            <p>{{ endpoint.note }}</p>
            <template v-if="endpoint.request">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Request</p>
                <pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs leading-relaxed text-slate-100">{{ endpoint.request }}</pre>
            </template>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Response</p>
            <pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs leading-relaxed text-slate-100">{{ endpoint.response }}</pre>
        </div>

        <ul class="list-disc space-y-1 pl-5">
            <li>The first line of your post becomes <code>title</code>; <code>content</code> is the rest as HTML (only p, br, strong, em, u, s, ul, ol, li, a, blockquote, h2, h3).</li>
            <li><code>tags</code> is an array of strings (empty if the post has none), taken from the Tags field when creating a post.</li>
            <li><code>cover_image</code> is the first uploaded image (or <code>null</code>). <code>media</code> lists every uploaded file.</li>
            <li>Uploaded file URLs must be publicly reachable. The <code>id</code> in the post response is required; <code>url</code> is the link shown for the live post.</li>
            <li>Posts are published immediately (<code>status: "published"</code>).</li>
        </ul>
    </div>
</template>

<script>
// Keep in sync with docs/website-blog-api.md and App\Services\Platforms\WebsiteService
const endpoints = [
    {
        method: 'GET',
        path: '/me',
        title: 'Test connection',
        note: 'Used by the "Test connection" button. Any authenticated 200 means success.',
        request: null,
        response: '200 OK\n{ "name": "Bilal" }   // body is optional',
    },
    {
        method: 'POST',
        path: '/media',
        title: 'Upload one file',
        note: 'Called once per attached image, video or audio file, before the post is created.',
        request: 'multipart/form-data\n  file: (the file)\n  type: "image" | "video" | "audio"',
        response: '201 Created\n{ "url": "https://yoursite.com/uploads/abc.jpg" }',
    },
    {
        method: 'POST',
        path: '/posts',
        title: 'Create a published post',
        note: 'Called once per post with the content and the URLs returned by /media.',
        request: 'application/json\n{\n  "title": "First line of the post (max 150 chars)",\n  "content": "<p>HTML body</p>",\n  "excerpt": "Plain-text summary, up to 200 chars",\n  "status": "published",\n  "tags": ["AI", "PHP"],\n  "cover_image": "https://yoursite.com/uploads/abc.jpg",   // or null\n  "media": [ { "type": "image", "url": "https://yoursite.com/uploads/abc.jpg" } ]\n}',
        response: '201 Created\n{ "id": 42, "url": "https://yoursite.com/blog/my-post-slug" }',
    },
];

export default {
    name: 'WebsiteApiGuide',
    setup() {
        return { endpoints };
    },
};
</script>
