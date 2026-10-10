<template>
    <div class="home">
        <header class="home-nav">
            <span class="home-brand">Content Distribution</span>
            <nav>
                <router-link to="/login" class="link">Login</router-link>
                <router-link to="/register" class="btn btn-light">Get started</router-link>
            </nav>
        </header>

        <section class="hero">
            <h1>Write once. Publish everywhere.</h1>
            <p>
                Content Distribution System lets you post text, images, videos and datasets
                to Facebook, LinkedIn, YouTube, TikTok and Kaggle from one place, now or on a schedule.
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
                    <div class="icon">{{ f.icon }}</div>
                    <h3>{{ f.title }}</h3>
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

        <section class="section">
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
        </footer>
    </div>
</template>

<script>
export default {
    name: 'Home',
    setup() {
        const features = [
            { icon: '🔗', title: 'Multi-platform posting', text: 'Send one post to several platforms at once instead of repeating yourself.' },
            { icon: '🖼️', title: 'Text, images, video, datasets', text: 'Attach media and datasets, with each platform receiving the format it supports.' },
            { icon: '⏰', title: 'Scheduled posting', text: 'Pick a date and time. Scheduled posts are published automatically by a background queue.' },
            { icon: '🔌', title: 'Platform management', text: 'Connect, test and disconnect your social accounts from a single page.' },
            { icon: '📜', title: 'Post history', text: 'See what was published, where, and whether each platform succeeded or failed.' },
            { icon: '🔒', title: 'Secure by design', text: 'Token-based authentication, and platform credentials are stored encrypted.' },
        ];
        const platforms = ['Facebook', 'LinkedIn', 'YouTube', 'TikTok', 'Kaggle'];
        return { features, platforms, year: new Date().getFullYear() };
    },
};
</script>

<style scoped>
.home { min-height: 100vh; color: #2c3e50; background: #fff; }
.home-nav {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 2rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff;
}
.home-brand { font-weight: bold; font-size: 1.25rem; }
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
.card .icon { font-size: 1.75rem; margin-bottom: 0.5rem; }
.card h3 { margin-bottom: 0.5rem; }
.card p { color: #555; line-height: 1.5; }
.chips { display: flex; justify-content: center; gap: 0.75rem; flex-wrap: wrap; }
.chip { padding: 0.5rem 1.25rem; background: #fff; border-radius: 999px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); font-weight: 600; }
.steps { max-width: 520px; margin: 0 auto 2rem; text-align: left; line-height: 2; }
.home-footer { padding: 1.5rem; text-align: center; color: #888; background: #f5f5f5; }
</style>
