<template>
    <LegalLayout title="Data Deletion Instructions" updated="October 12, 2026" v-slot="{ contactEmail }">
        <p>
            Content Distribution System (the "Service") lets you publish posts to your own Facebook, Instagram, LinkedIn,
            YouTube, TikTok and X accounts. You can have the information the Service holds about you deleted at any
            time. This page explains how, including for data we received from Facebook or Instagram.
        </p>

        <h2>What we store</h2>
        <ul>
            <li>Your account details: name, email address and a hashed password.</li>
            <li>The access tokens and identifiers (for example a Facebook Page ID) for each platform you connected.</li>
            <li>Your posts, schedules, publishing history and any uploaded images, videos or audio.</li>
        </ul>

        <h2>Option 1: Disconnect a platform yourself</h2>
        <p>
            This removes the stored credentials for that platform straight away, so the Service can no longer access
            your account there.
        </p>
        <ul>
            <li>Log in, open <router-link to="/platforms">Platforms</router-link>, choose the platform and click
                <strong>Disconnect</strong>.</li>
        </ul>

        <h2>Option 2: Remove the app from Facebook</h2>
        <p>This also revokes the Service's access from Facebook's side.</p>
        <ul>
            <li>In Facebook, open <strong>Settings &amp; privacy</strong> &rarr; <strong>Settings</strong> &rarr;
                <strong>Apps and Websites</strong> (shown as <strong>Business Integrations</strong> on some accounts).</li>
            <li>Find <strong>Content Distribution System</strong>, select it and click <strong>Remove</strong>.</li>
            <li>Tick the option to delete the posts, photos and videos the app published on your behalf if you also
                want those removed from Facebook.</li>
        </ul>

        <h2>Option 3: Ask us to delete everything</h2>
        <p>
            To delete your account and all data linked to it, email
            <a :href="'mailto:' + contactEmail + '?subject=Data%20deletion%20request'">{{ contactEmail }}</a>
            with the subject <strong>Data deletion request</strong>, from the email address you registered with (or tell
            us which address your account uses). We will confirm your request by email and delete your account, connected
            platform credentials, posts, schedules and uploaded files within 30 days.
        </p>

        <h2>Good to know</h2>
        <ul>
            <li>Posts the Service already published stay on those platforms. Delete them there if you want them gone.</li>
            <li>We may keep limited information where the law requires it, as described in our
                <router-link to="/privacy">Privacy Policy</router-link>.</li>
            <li>Deletion can't be undone.</li>
        </ul>
    </LegalLayout>
</template>

<script>
import LegalLayout from '../components/LegalLayout.vue';

export default {
    name: 'DataDeletion',
    components: { LegalLayout },
};
</script>
