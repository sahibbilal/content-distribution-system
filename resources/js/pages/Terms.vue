<template>
    <LegalLayout title="Terms of Service" v-slot="{ contactEmail }">
        <p>
            These terms govern your use of Content Distribution System (the "Service"), a web application that lets you
            create posts and publish them to your connected social media accounts. By creating an account or using the
            Service you agree to these terms.
        </p>

        <h2>1. Your account</h2>
        <ul>
            <li>You must provide accurate information and verify your email address before using the Service.</li>
            <li>You are responsible for keeping your password secure and for all activity under your account.</li>
            <li>You must be old enough to enter a binding agreement and to use each connected platform.</li>
        </ul>

        <h2>2. What the Service does</h2>
        <p>
            When you connect an account on Facebook, LinkedIn, YouTube or TikTok, the Service publishes the content you
            create to that account, immediately or at the time you schedule. The Service acts only on your instructions.
            You can disconnect any platform at any time from the Platforms page.
        </p>

        <h2>3. Your content</h2>
        <ul>
            <li>You keep ownership of everything you post. You grant us only the limited permission needed to store,
                process and transmit your content to the platforms you choose.</li>
            <li>You are solely responsible for your content and for having the rights to publish it.</li>
            <li>Your use of each connected platform must comply with that platform's own terms and policies.</li>
        </ul>

        <h2>4. Acceptable use</h2>
        <p>You agree not to use the Service to:</p>
        <ul>
            <li>post unlawful, harassing, deceptive, infringing or malicious content;</li>
            <li>send spam or automate abusive behaviour on any platform;</li>
            <li>attempt to access other users' accounts or data, or disrupt the Service;</li>
            <li>violate the terms of Facebook, LinkedIn, YouTube, TikTok or any other connected service.</li>
        </ul>

        <h2>5. Third-party platforms</h2>
        <p>
            The Service depends on third-party APIs that we do not control. Platforms may change their rules, limit
            access, or reject content, and posts can fail or be removed as a result. We are not responsible for the
            actions of third-party platforms.
        </p>

        <h2>6. Availability and changes</h2>
        <p>
            We aim to keep the Service available but do not guarantee uninterrupted operation, including for scheduled
            posts. We may change or discontinue features, and may update these terms. Continued use after a change
            means you accept the updated terms.
        </p>

        <h2>7. Termination</h2>
        <p>
            You may stop using the Service and request deletion of your account at any time. We may suspend or end
            access if you breach these terms or misuse the Service.
        </p>

        <h2>8. Disclaimer and limitation of liability</h2>
        <p>
            The Service is provided "as is" and "as available", without warranties of any kind. To the extent permitted
            by law, we are not liable for indirect or consequential losses, lost data, lost profits, or content that
            fails to publish or is removed by a third-party platform.
        </p>

        <h2>9. Privacy</h2>
        <p>
            How we handle your information is described in our <router-link to="/privacy">Privacy Policy</router-link>.
        </p>

        <h2>10. Contact</h2>
        <p>Questions about these terms: <a :href="'mailto:' + contactEmail">{{ contactEmail }}</a></p>
    </LegalLayout>
</template>

<script>
import LegalLayout from '../components/LegalLayout.vue';

export default {
    name: 'Terms',
    components: { LegalLayout },
};
</script>
