<template>
    <div class="min-h-screen bg-white text-slate-900">
        <!-- Nav -->
        <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/80 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <BrandLogo :size="46" />
                <nav class="flex items-center gap-2 sm:gap-3">
                    <a href="#pricing" class="btn btn-ghost hidden sm:inline-flex">Pricing</a>
                    <router-link to="/login" class="btn btn-ghost">Log in</router-link>
                    <router-link to="/register" class="btn btn-primary">Get started</router-link>
                </nav>
            </div>
        </header>

        <div v-if="!content" class="mx-auto max-w-6xl px-4 py-24 sm:px-6">
            <div class="mx-auto h-10 w-2/3 animate-pulse rounded-lg bg-slate-200"></div>
            <div class="mx-auto mt-5 h-5 w-1/2 animate-pulse rounded bg-slate-100"></div>
        </div>

        <template v-if="content">
            <!-- Hero -->
            <section class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
                <div class="pointer-events-none absolute -top-24 left-1/2 h-72 w-[40rem] -translate-x-1/2 rounded-full bg-cyan-300/30 blur-3xl"></div>
                <div class="relative mx-auto max-w-4xl px-4 pt-20 pb-24 text-center sm:px-6 sm:pt-28">
                    <h1 class="text-4xl font-bold tracking-tight text-brand-950 sm:text-6xl">{{ content.hero.title }}</h1>
                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-600">{{ content.hero.subtitle }}</p>
                    <div class="mt-10 flex flex-wrap justify-center gap-3">
                        <router-link to="/register" class="btn btn-primary px-6 py-3">
                            Create account <ArrowRight class="h-4 w-4" />
                        </router-link>
                        <router-link to="/login" class="btn btn-secondary px-6 py-3">Log in</router-link>
                    </div>
                    <div class="mt-14 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-sm text-slate-500">
                        <span>Publish to</span>
                        <span v-for="p in content.platforms" :key="p" class="flex items-center gap-2 font-medium text-slate-700">
                            <PlatformIcon :type="p.toLowerCase()" :size="20" /> {{ p }}
                        </span>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">What you can do</h2>
                </div>
                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="f in content.features" :key="f.title" class="card p-6 transition hover:-translate-y-0.5 hover:shadow-pop">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-xl ring-1 ring-brand-100">
                            <span v-if="f.icon">{{ f.icon }}</span>
                            <Layers v-else class="h-5 w-5 text-brand-600" />
                        </span>
                        <h3 class="mt-4 text-lg font-semibold">{{ f.title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ f.text }}</p>
                    </div>
                </div>
            </section>

            <!-- How it works -->
            <section class="border-y border-slate-200 bg-slate-50">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8">
                    <h2 class="text-center text-3xl font-bold tracking-tight sm:text-4xl">How it works</h2>
                    <ol class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                        <li v-for="(step, i) in steps" :key="step.title">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">{{ i + 1 }}</span>
                            <h3 class="mt-4 font-semibold">{{ step.title }}</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-600">{{ step.text }}</p>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- Pricing -->
            <section id="pricing" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Pricing</h2>
                    <p class="mt-3 text-slate-600">Simple monthly plans. Both let you post to all connected platforms.</p>
                </div>
                <div class="mx-auto mt-12 grid max-w-3xl gap-6 sm:grid-cols-2">
                    <div v-for="plan in content.plans" :key="plan.name" class="card relative flex flex-col p-7"
                        :class="plan.featured ? 'ring-2! ring-brand-600! shadow-pop' : ''">
                        <span v-if="plan.featured" class="absolute -top-3 left-7 rounded-full bg-brand-600 px-3 py-0.5 text-xs font-semibold text-white">Most popular</span>
                        <h3 class="text-lg font-semibold">{{ plan.name }}</h3>
                        <p class="mt-4 flex items-baseline gap-1">
                            <span class="text-4xl font-bold tracking-tight">Rs {{ plan.price }}</span>
                            <span class="text-sm text-slate-500">/ month</span>
                        </p>
                        <ul class="mt-6 flex-1 space-y-3">
                            <li v-for="item in plan.features" :key="item" class="flex items-start gap-2.5 text-sm text-slate-700">
                                <Check class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" /> {{ item }}
                            </li>
                        </ul>
                        <router-link to="/register" class="btn mt-8" :class="plan.featured ? 'btn-primary' : 'btn-secondary'">Get started</router-link>
                    </div>
                </div>

                <div class="card mx-auto mt-10 max-w-3xl p-7">
                    <h3 class="text-lg font-semibold">How to join</h3>
                    <ol class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <li class="flex gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">1</span>
                            <span><router-link to="/register" class="font-medium text-brand-700 hover:text-brand-800">Create your account</router-link> and verify your email.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">2</span>
                            <span>Pay for the plan you want using one of the methods below.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">3</span>
                            <span>Email your payment proof and the email you registered with to
                                <a :href="'mailto:' + content.contact_email" class="font-medium text-brand-700 hover:text-brand-800">{{ content.contact_email }}</a>.
                                We activate your plan once the payment is confirmed.</span>
                        </li>
                    </ol>
                    <div v-if="content.payment_methods.length" class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div v-for="m in content.payment_methods" :key="m.name" class="rounded-lg bg-slate-50 p-4 text-sm ring-1 ring-slate-900/5">
                            <strong class="text-slate-900">{{ m.name }}</strong>
                            <div v-for="d in m.details" :key="d.label" class="mt-1 text-slate-600">{{ d.label }}: <span class="font-medium text-slate-800">{{ d.value }}</span></div>
                        </div>
                    </div>
                    <p v-else class="mt-6 text-sm text-slate-600">
                        Contact <a :href="'mailto:' + content.contact_email" class="font-medium text-brand-700 hover:text-brand-800">{{ content.contact_email }}</a> for payment details.
                    </p>
                </div>
            </section>

            <!-- CTA -->
            <section class="px-4 pb-20 sm:px-6 lg:px-8">
                <div class="relative mx-auto max-w-6xl overflow-hidden rounded-3xl bg-brand-950 px-6 py-16 text-center">
                    <div class="pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full bg-cyan-400/20 blur-3xl"></div>
                    <h2 class="relative text-3xl font-bold tracking-tight text-white sm:text-4xl">Ready to publish everywhere?</h2>
                    <p class="relative mx-auto mt-3 max-w-xl text-brand-200">Connect your accounts and send your first post in minutes.</p>
                    <router-link to="/login" class="btn relative mt-8 bg-white px-6 py-3 text-brand-950 hover:bg-brand-50">Log in to get started</router-link>
                </div>
            </section>
        </template>

        <footer class="border-t border-slate-200 bg-slate-50 py-8 text-center text-sm text-slate-500">
            &copy; {{ year }} Content Distribution System
            <span class="mx-1">&middot;</span>
            <router-link to="/terms" class="hover:text-slate-900">Terms of Service</router-link>
            <span class="mx-1">&middot;</span>
            <router-link to="/privacy" class="hover:text-slate-900">Privacy Policy</router-link>
        </footer>
    </div>
</template>

<script>
import { onMounted } from 'vue';
import { ArrowRight, Check, Layers } from 'lucide-vue-next';
import BrandLogo from '../components/BrandLogo.vue';
import PlatformIcon from '../components/PlatformIcon.vue';
import { siteContent, loadSiteContent } from '../siteContent.js';

export default {
    components: { BrandLogo, PlatformIcon, ArrowRight, Check, Layers },
    name: 'Home',
    setup() {
        onMounted(() => loadSiteContent().catch(() => {}));
        const steps = [
            { title: 'Connect', text: 'Connect your accounts on the Platforms page.' },
            { title: 'Create', text: 'Create a post and choose which platforms it goes to.' },
            { title: 'Publish', text: 'Publish immediately or schedule it for later.' },
            { title: 'Track', text: 'Track the status of every post per platform.' },
        ];
        return { content: siteContent, year: new Date().getFullYear(), steps };
    },
};
</script>
