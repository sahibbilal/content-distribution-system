<template>
    <LegalLayout title="Privacy Policy" v-slot="{ contactEmail }">
        <p>
            This policy explains what information Content Distribution System (the "Service") collects, how it is used,
            and the choices you have. The Service lets you publish posts to your own Facebook, LinkedIn, YouTube and
            TikTok accounts from one place.
        </p>

        <h2>1. Information we collect</h2>
        <ul>
            <li><strong>Account information:</strong> your name and email address, and your password, which is stored
                only as a one-way hash.</li>
            <li><strong>Connected platform credentials:</strong> access tokens and related identifiers (for example a
                page or account ID) that you provide or authorise when you connect a platform. These are stored
                encrypted.</li>
            <li><strong>Content you create:</strong> post text, uploaded images and videos, schedules, and the publishing
                status of each post per platform.</li>
            <li><strong>Technical data:</strong> session and security cookies, an authentication token kept in your
                browser's local storage, and standard server logs.</li>
        </ul>

        <h2>2. How we use it</h2>
        <ul>
            <li>To create your account, verify your email and keep you signed in.</li>
            <li>To publish your content, immediately or on schedule, to the platforms you have connected, and to show
                you the result.</li>
            <li>To secure the Service, prevent abuse and fix problems.</li>
        </ul>
        <p>We do not sell your personal information and do not use it for advertising.</p>

        <h2>3. Data from connected platforms</h2>
        <p>
            We access a connected platform only with the permissions you grant, and only to publish the content you
            create and to show your connection status. We do not read your private messages or use platform data for
            any other purpose.
        </p>
        <p>
            For YouTube, the Service's use of information received from Google APIs adheres to the
            <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>,
            including the Limited Use requirements.
        </p>

        <h2>4. Sharing</h2>
        <p>
            Your content and credentials are sent to Facebook (Meta), LinkedIn, YouTube (Google) or TikTok only when you
            connect and publish to that platform, and are then governed by that platform's own privacy policy. We use
            hosting and email services to run the Service. We do not share your information with anyone else, except
            where required by law.
        </p>

        <h2>5. Cookies and local storage</h2>
        <p>
            We use cookies and local storage to keep you signed in and to protect against forgery. If you choose
            "Remember me", your email address and sign-in token are kept in your browser for up to 30 days. Pages that
            load the Facebook SDK may cause Facebook to set its own cookies.
        </p>

        <h2>6. Retention and deletion</h2>
        <ul>
            <li>Disconnecting a platform deletes its stored credentials.</li>
            <li>You can ask us to delete your account and all associated data at any time using the contact address
                below. We will do so within a reasonable time, except where we must keep information by law.
                See <router-link to="/data-deletion">Data Deletion Instructions</router-link> for the steps.</li>
        </ul>

        <h2>7. Security</h2>
        <p>
            Passwords are hashed, platform credentials are encrypted at rest, and the Service is served over HTTPS. No
            system is perfectly secure, so please use a strong, unique password.
        </p>

        <h2>8. Your choices</h2>
        <p>
            You can access, correct or delete your information, or withdraw a platform's access, by contacting us. You
            can also revoke the Service's access directly from each platform's own security settings.
        </p>

        <h2>9. Changes</h2>
        <p>We may update this policy and will change the "Last updated" date when we do.</p>

        <h2>10. Contact</h2>
        <p>Privacy questions or data requests: <a :href="'mailto:' + contactEmail">{{ contactEmail }}</a></p>
        <p>See also our <router-link to="/terms">Terms of Service</router-link>.</p>
    </LegalLayout>
</template>

<script>
import LegalLayout from '../components/LegalLayout.vue';

export default {
    name: 'Privacy',
    components: { LegalLayout },
};
</script>
