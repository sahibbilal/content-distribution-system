<template>
    <div>
        <PageHeader title="Calendar" description="Everything scheduled, by day.">
            <div v-if="isAdmin" class="inline-flex rounded-lg bg-white p-0.5 shadow-sm ring-1 ring-slate-300" role="group" aria-label="Whose schedule">
                <button v-for="opt in scopes" :key="opt.value" type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                    :class="scope === opt.value ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-50'" @click="setScope(opt.value)">
                    {{ opt.label }}
                </button>
            </div>
            <router-link to="/create-post" class="btn btn-primary"><Plus class="h-4 w-4" /> Schedule a post</router-link>
        </PageHeader>

        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 sm:px-5">
                <h2 class="text-lg font-semibold text-slate-900" data-month>{{ monthLabel }}</h2>
                <div class="flex items-center gap-1.5">
                    <button type="button" class="btn btn-secondary btn-sm" @click="goToday">Today</button>
                    <button type="button" class="btn btn-ghost p-2!" aria-label="Previous month" @click="shiftMonth(-1)"><ChevronLeft class="h-5 w-5" /></button>
                    <button type="button" class="btn btn-ghost p-2!" aria-label="Next month" @click="shiftMonth(1)"><ChevronRight class="h-5 w-5" /></button>
                </div>
            </div>

            <div class="grid grid-cols-7 border-b border-slate-100 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                <div v-for="d in weekdays" :key="d" class="py-2">{{ d }}</div>
            </div>

            <div class="grid grid-cols-7 transition-opacity" :class="{ 'opacity-60': loading }" data-grid>
                <div v-for="cell in cells" :key="cell.key" role="button" tabindex="0" :data-day="cell.key"
                    class="min-h-[4.5rem] cursor-pointer border-b border-r border-slate-100 p-1.5 transition hover:bg-slate-50 sm:min-h-[7.5rem] sm:p-2"
                    :class="[cell.inMonth ? 'bg-white' : 'bg-slate-50/60', selected === cell.key ? 'ring-2 ring-inset ring-brand-600' : '']"
                    @click="selected = cell.key" @keyup.enter="selected = cell.key">
                    <div class="flex items-center justify-between">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium"
                            :class="cell.isToday ? 'bg-brand-600 text-white' : cell.inMonth ? 'text-slate-700' : 'text-slate-400'">{{ cell.day }}</span>
                        <span v-if="cell.items.length" class="rounded-full bg-brand-100 px-1.5 text-[11px] font-semibold text-brand-800 sm:hidden">{{ cell.items.length }}</span>
                    </div>

                    <!-- Desktop: a pill per scheduled post with its platform icons -->
                    <div class="mt-1 hidden space-y-1 sm:block">
                        <router-link v-for="item in cell.items.slice(0, 3)" :key="item.id" :to="`/posts/${item.post_id}`" data-item
                            class="flex items-center gap-1.5 rounded-md px-1.5 py-1 text-[11px] font-medium ring-1 ring-inset transition hover:brightness-95"
                            :class="statusClass(item.status)" :title="item.content" @click.stop>
                            <span class="shrink-0">{{ formatTime(item.scheduled_at) }}</span>
                            <span class="flex items-center gap-0.5">
                                <PlatformIcon v-for="type in item.platforms" :key="type" :type="type" :size="14" />
                            </span>
                        </router-link>
                        <p v-if="cell.items.length > 3" class="px-1 text-[11px] font-medium text-slate-500">+{{ cell.items.length - 3 }} more</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Selected day -->
        <section class="card mt-6" data-agenda>
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="card-title">{{ selectedLabel }}</h2>
            </div>
            <div v-if="selectedItems.length === 0" class="px-5 py-10 text-center">
                <CalendarClock class="mx-auto h-8 w-8 text-slate-300" />
                <p class="mt-3 text-sm text-slate-500">Nothing scheduled on this day</p>
            </div>
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="item in selectedItems" :key="item.id" data-agenda-item>
                    <router-link :to="`/posts/${item.post_id}`" class="flex items-start gap-4 p-5 transition hover:bg-slate-50">
                        <div class="w-16 shrink-0 text-sm font-semibold text-slate-900">{{ formatTime(item.scheduled_at) }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 whitespace-pre-line break-words text-sm text-slate-800">{{ item.content }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                <span v-for="type in item.platforms" :key="type" class="inline-flex items-center gap-1.5">
                                    <PlatformIcon :type="type" :size="16" /> {{ platformLabel(type) }}
                                </span>
                                <span v-if="item.user" class="inline-flex items-center gap-1"><User class="h-3.5 w-3.5" /> {{ item.user.name }}</span>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span class="badge" :class="statusBadge(item.status)">{{ statusText(item.status) }}</span>
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-brand-700">
                                <template v-if="item.status === 'pending'"><Pencil class="h-3.5 w-3.5" /> Edit</template>
                                <template v-else>View <ChevronRight class="h-3.5 w-3.5" /></template>
                            </span>
                        </div>
                    </router-link>
                </li>
            </ul>
        </section>

        <div v-if="error" class="alert alert-error mt-4">{{ error }}</div>
    </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { CalendarClock, ChevronLeft, ChevronRight, Pencil, Plus, User } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import PlatformIcon from '../components/PlatformIcon.vue';
import { platformLabel } from '../platforms.js';

// local YYYY-MM-DD, so a post lands on the day the viewer sees it
const dayKey = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

export default {
    name: 'Calendar',
    components: { CalendarClock, ChevronLeft, ChevronRight, Pencil, Plus, User, PageHeader, PlatformIcon },
    setup() {
        const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const today = new Date();
        const month = ref(new Date(today.getFullYear(), today.getMonth(), 1));
        const items = ref([]);
        const selected = ref(dayKey(today));
        const loading = ref(false);
        const error = ref('');
        const scope = ref('mine');
        const scopes = [{ value: 'mine', label: 'My accounts' }, { value: 'all', label: 'All users' }];
        let requestId = 0;

        let isAdmin = false;
        try {
            isAdmin = !!JSON.parse(localStorage.getItem('user') || 'null')?.is_admin;
        } catch (e) {
            // ignore a corrupt stored user
        }

        // 6 full weeks starting on the Sunday on/before the 1st
        const gridStart = computed(() => {
            const first = month.value;
            return new Date(first.getFullYear(), first.getMonth(), 1 - first.getDay());
        });

        const byDay = computed(() => {
            const map = {};
            for (const item of items.value) {
                (map[dayKey(new Date(item.scheduled_at))] ||= []).push(item);
            }
            return map;
        });

        const cells = computed(() => Array.from({ length: 42 }, (_, i) => {
            const d = new Date(gridStart.value.getFullYear(), gridStart.value.getMonth(), gridStart.value.getDate() + i);
            const key = dayKey(d);
            return {
                key,
                day: d.getDate(),
                inMonth: d.getMonth() === month.value.getMonth(),
                isToday: key === dayKey(today),
                items: byDay.value[key] || [],
            };
        }));

        const monthLabel = computed(() => month.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));
        const selectedItems = computed(() => byDay.value[selected.value] || []);
        const selectedLabel = computed(() => {
            const [y, m, d] = selected.value.split('-').map(Number);
            return new Date(y, m - 1, d).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        });

        const load = async () => {
            const id = ++requestId;
            loading.value = true;
            error.value = '';
            const from = gridStart.value;
            const to = new Date(from.getFullYear(), from.getMonth(), from.getDate() + 42);
            try {
                const response = await axios.get('/schedules/calendar', {
                    params: { from: from.toISOString(), to: to.toISOString(), scope: scope.value === 'all' ? 'all' : undefined },
                });
                if (id === requestId) items.value = response.data;
            } catch (err) {
                if (id === requestId) {
                    items.value = [];
                    error.value = err.response?.data?.message || 'Could not load the calendar.';
                }
            } finally {
                if (id === requestId) loading.value = false;
            }
        };

        const shiftMonth = (n) => {
            month.value = new Date(month.value.getFullYear(), month.value.getMonth() + n, 1);
            selected.value = dayKey(month.value);
        };
        const goToday = () => {
            month.value = new Date(today.getFullYear(), today.getMonth(), 1);
            selected.value = dayKey(today);
        };
        const setScope = (value) => { scope.value = value; };

        watch([month, scope], load);

        const formatTime = (iso) => new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const statusText = (s) => ({ pending: 'Scheduled', processing: 'Publishing', completed: 'Published', failed: 'Failed' })[s] || s;
        const statusBadge = (s) => ({ pending: 'badge-warning', processing: 'badge-neutral', completed: 'badge-success', failed: 'badge-danger' })[s] || 'badge-neutral';
        const statusClass = (s) => ({
            pending: 'bg-brand-50 text-brand-800 ring-brand-200',
            processing: 'bg-amber-50 text-amber-800 ring-amber-200',
            completed: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            failed: 'bg-red-50 text-red-800 ring-red-200',
        })[s] || 'bg-slate-50 text-slate-700 ring-slate-200';

        onMounted(load);

        return {
            weekdays, cells, selected, selectedItems, selectedLabel, monthLabel, loading, error, scope, scopes, isAdmin,
            shiftMonth, goToday, setScope, formatTime, statusText, statusBadge, statusClass, platformLabel,
        };
    },
};
</script>
