<template>
    <div class="home">
        <header class="home-nav">
            <BrandLogo :size="44" pill />
            <nav>
                <a href="#pricing" class="link">Pricing</a>
                <router-link to="/login" class="link">Login</router-link>
                <router-link to="/register" class="btn btn-light">Get started</router-link>
            </nav>
        </header>

        <section class="hero">
            <h1>Write once. Publish everywhere.</h1>
            <p>
                Content Distribution System lets you post text, images and videos
                to Facebook, LinkedIn, YouTube and TikTok from one place, now or on a schedule.
            </p>
            <div class="hero-actions">
                <router-link to="/login" class="btn btn-light">Login</router-link>
                <router-link to="/register" class="btn btn-outline">Create account</router-link>
            </div>
        </section>

        <section class="section">
            <h2>What you can do</h2>
            <div class="grid">
                <div v-for="f in features" :key="f.title" class="card">
                    <div class="card-head">
                        <span class="icon">{{ f.icon }}</span>
                        <h3>{{ f.title }}</h3>
                    </div>
                    <p>{{ f.text }}</p>
                </div>
            </div>
        </section>

        <section class="section section-alt">
            <h2>Supported platforms</h2>
            <div class="chips">
                <span v-for="p in platforms" :key="p" class="chip">{{ p }}</span>
            </div>
        </section>

        <section id="pricing" class="section">
            <h2>Pricing</h2>
            <p class="section-sub">Simple monthly plans. Both let you post to all connected platforms.</p>
            <div class="plans">
                <div v-for="plan in plans" :key="plan.name" class="plan" :class="{ featured: plan.featured }">
                    <h3>{{ plan.name }}</h3>
                    <div class="price">Rs {{ plan.price }}<span> / month</span></div>
                    <ul>
                        <li v-for="item in plan.features" :key="item">✓ {{ item }}</li>
                    </ul>
                </div>
            </div>

            <div class="payment">
                <h3>How to join</h3>
                <ol>
                    <li><router-link to="/register">Create your account</router-link> and verify your email.</li>
                    <li>Pay for the plan you want using one of the methods below.</li>
                    <li>Email your payment proof and the email you registered with to
                        <a :href="'mailto:' + contactEmail">{{ contactEmail }}</a>. We activate your plan once the payment is confirmed.</li>
                </ol>
                <div v-if="paymentMethods.length" class="methods">
                    <div v-for="m in paymentMethods" :key="m.name" class="method">
                        <strong>{{ m.name }}</strong>
                        <div v-for="[label, value] in m.details" :key="label">{{ label }}: {{ value }}</div>
                    </div>
                </div>
                <p v-else class="section-sub">
                    Contact <a :href="'mailto:' + contactEmail">{{ contactEmail }}</a> for payment details.
                </p>
            </div>
        </section>

        <section class="section section-alt">
            <h2>How it works</h2>
            <ol class="steps">
                <li><strong>Connect</strong> your accounts on the Platforms page.</li>
                <li><strong>Create</strong> a post and choose which platforms it goes to.</li>
                <li><strong>Publish</strong> immediately or schedule it for later.</li>
                <li><strong>Track</strong> the status of every post per platform.</li>
            </ol>
            <router-link to="/login" class="btn btn-primary">Login to get started</router-link>
        </section>

        <footer class="home-footer">
            &copy; {{ year }} Content Distribution System
            &middot; <router-link to="/terms">Terms of Service</router-link>
            &middot; <router-link to="/privacy">Privacy Policy</router-link>
        </footer>
    </div>
</template>

<script>
import BrandLogo from '../components/BrandLogo.vue';
import { contactEmail, paymentMethods } from '../config.js';

export default {
    components: { BrandLogo },
    name: 'Home',
    setup() {
        const features = [
            { icon: '🔗', title: 'Multi-platform posting', text: 'Send one post to several platforms at once instead of repeating yourself.' },
            { icon: '🖼️', title: 'Text, images, video', text: 'Attach media with each platform receiving the format it supports.' },
            { icon: '⏰', title: 'Scheduled posting', text: 'Pick a date and time. Scheduled posts are published automatically by a background queue.' },
            { icon: '🔌', title: 'Platform management', text: 'Connect, test and disconnect your social accounts from a single page.' },
            { icon: '📜', title: 'Post history', text: 'See what was published, where, and whether each platform succeeded or failed.' },
            { icon: '🔒', title: 'Secure by design', text: 'Token-based authentication, and platform credentials are stored encrypted.' },
        ];
        const platforms = ['Facebook', 'LinkedIn', 'YouTube', 'TikTok'];
        const plans = [
            {
                name: 'Standard',
                price: '500',
                features: [
                    'Post to all connected platforms',
                    'Facebook, LinkedIn, YouTube and TikTok',
                    'Text, image and video posts',
                    'Scheduled posting and post history',
                    'You write your own content',
                ],
            },
            {
                name: 'Standard + AI',
                price: '2,000',
                featured: true,
                features: [
                    'Everything in Standard',
                    'AI-generated content (coming soon)',
                ],
            },
        ];
        return { features, platforms, plans, contactEmail, paymentMethods, year: new Date().getFullYear() };
    },
};
</script>

<style scoped>
.home { min-height: 100vh; color: #2c3e50; background: #fff; }
.home-nav {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 2rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff;
}
.home-nav nav { display: flex; align-items: center; gap: 1rem; }
.link { color: #fff; text-decoration: none; }
.hero {
    text-align: center; padding: 4rem 1.5rem 5rem; color: #fff;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.hero h1 { font-size: 2.5rem; margin-bottom: 1rem; }
.hero p { max-width: 640px; margin: 0 auto 2rem; font-size: 1.1rem; line-height: 1.6; opacity: 0.95; }
.hero-actions { display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; }
.btn {
    display: inline-block; padding: 0.75rem 1.5rem; border-radius: 6px;
    text-decoration: none; font-weight: 600; border: 2px solid transparent;
}
.btn-light { background: #fff; color: #667eea; }
.btn-outline { border-color: #fff; color: #fff; }
.btn-primary { background: #667eea; color: #fff; }
.section { max-width: 1000px; margin: 0 auto; padding: 3rem 1.5rem; text-align: center; }
.section-alt { max-width: none; background: #f5f5f5; }
.section h2 { margin-bottom: 1.5rem; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; text-align: left; }
.card { padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); background: #fff; }
.card-head { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.5rem; }
.card .icon { font-size: 1.5rem; line-height: 1; }
.card h3 { margin: 0; }
.card p { color: #555; line-height: 1.5; }
.chips { display: flex; justify-content: center; gap: 0.75rem; flex-wrap: wrap; }
.chip { padding: 0.5rem 1.25rem; background: #fff; border-radius: 999px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); font-weight: 600; }
.section-sub { color: #666; margin-bottom: 1.5rem; }
.plans { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; max-width: 640px; margin: 0 auto 2rem; text-align: left; }
.plan { padding: 1.75rem; border: 2px solid #e5e7f0; border-radius: 10px; background: #fff; }
.plan.featured { border-color: #667eea; box-shadow: 0 4px 16px rgba(102, 126, 234, 0.25); }
.plan h3 { margin-bottom: 0.5rem; }
.price { font-size: 2rem; font-weight: bold; color: #667eea; margin-bottom: 1rem; }
.price span { font-size: 1rem; font-weight: normal; color: #888; }
.plan ul { list-style: none; line-height: 1.9; color: #444; }
.payment { max-width: 640px; margin: 0 auto; padding: 1.5rem; background: #f5f5f5; border-radius: 10px; text-align: left; }
.payment h3 { margin-bottom: 0.75rem; }
.payment ol { margin: 0 0 1rem 1.25rem; line-height: 1.7; }
.payment a { color: #667eea; }
.methods { display: grid; gap: 0.75rem; }
.method { padding: 0.75rem 1rem; background: #fff; border-radius: 6px; line-height: 1.6; }
.steps { max-width: 520px; margin: 0 auto 2rem; text-align: left; line-height: 2; }
.home-footer { padding: 1.5rem; text-align: center; color: #888; background: #f5f5f5; }
.home-footer a { color: #667eea; text-decoration: none; }
</style>
