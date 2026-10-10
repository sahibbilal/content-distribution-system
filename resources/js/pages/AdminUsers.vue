<template>
    <div class="admin">
        <h1>Users</h1>
        <AdminNav />

        <form class="search" @submit.prevent="search">
            <input v-model="query" type="text" placeholder="Search by name or email" />
            <button type="submit" class="btn-primary">Search</button>
        </form>

        <div v-if="error" class="error-message">{{ error }}</div>
        <div v-if="notice" class="success-message">{{ notice }}</div>

        <div class="panel">
            <div v-if="loading" class="muted">Loading...</div>
            <div v-else-if="!users.length" class="muted">No users found.</div>
            <div v-else class="table-wrap">
                <table>
                    <thead>
                        <tr><th>User</th><th>Role</th><th>Email</th><th>Joined</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id">
                            <td>
                                <strong>{{ user.name }}</strong>
                                <div class="muted">{{ user.email }}</div>
                            </td>
                            <td>
                                <select
                                    :value="user.is_admin ? 'admin' : 'user'"
                                    :disabled="user.id === currentUserId || busy === user.id"
                                    :title="user.id === currentUserId ? 'You cannot change your own role' : ''"
                                    @change="changeRole(user, $event.target.value)"
                                >
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </td>
                            <td>
                                <span v-if="user.email_verified_at" class="badge ok">Verified</span>
                                <template v-else>
                                    <span class="badge warn">Unverified</span>
                                    <button type="button" class="link-btn" :disabled="busy === user.id" @click="verify(user)">Mark verified</button>
                                    <button type="button" class="link-btn" :disabled="busy === user.id" @click="resend(user)">Resend email</button>
                                </template>
                            </td>
                            <td class="muted">{{ formatDate(user.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="lastPage > 1" class="pager">
                <button type="button" class="link-btn" :disabled="page <= 1" @click="load(page - 1)">&larr; Previous</button>
                <span class="muted">Page {{ page }} of {{ lastPage }} ({{ total }} users)</span>
                <button type="button" class="link-btn" :disabled="page >= lastPage" @click="load(page + 1)">Next &rarr;</button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import AdminNav from '../components/AdminNav.vue';

export default {
    name: 'AdminUsers',
    components: { AdminNav },
    setup() {
        const users = ref([]);
        const query = ref('');
        const page = ref(1);
        const lastPage = ref(1);
        const total = ref(0);
        const loading = ref(true);
        const busy = ref(null);
        const error = ref('');
        const notice = ref('');

        let currentUserId = null;
        try {
            currentUserId = JSON.parse(localStorage.getItem('user') || 'null')?.id ?? null;
        } catch (e) {
            // ignore a corrupt stored user
        }

        const fail = (err, fallback) => {
            const errors = err.response?.data?.errors;
            error.value = errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message || fallback;
        };

        const load = async (target = 1) => {
            loading.value = true;
            error.value = '';
            try {
                const { data } = await axios.get('/admin/users', { params: { page: target, q: query.value || undefined } });
                users.value = data.data;
                page.value = data.current_page;
                lastPage.value = data.last_page;
                total.value = data.total;
            } catch (err) {
                fail(err, 'Could not load users.');
            } finally {
                loading.value = false;
            }
        };

        const search = () => load(1);

        // Runs one row action and swaps the returned user into the list
        const act = async (user, request, successMessage, fallback) => {
            busy.value = user.id;
            error.value = '';
            notice.value = '';
            try {
                const { data } = await request();
                if (data.id) {
                    Object.assign(user, data);
                }
                notice.value = successMessage(data);
            } catch (err) {
                fail(err, fallback);
                // reset a changed <select> back to the stored role
                users.value = [...users.value];
            } finally {
                busy.value = null;
            }
        };

        const changeRole = (user, role) => act(
            user,
            () => axios.patch(`/admin/users/${user.id}`, { is_admin: role === 'admin' }),
            () => `${user.email} is now ${role === 'admin' ? 'an admin' : 'a regular user'}.`,
            'Could not change the role.'
        );

        const verify = (user) => act(
            user,
            () => axios.post(`/admin/users/${user.id}/verify`),
            () => `${user.email} marked as verified.`,
            'Could not verify the user.'
        );

        const resend = (user) => act(
            user,
            () => axios.post(`/admin/users/${user.id}/resend-verification`),
            (data) => data.message,
            'Could not send the email.'
        );

        const formatDate = (value) => new Date(value).toLocaleDateString();

        onMounted(() => load(1));

        return {
            users, query, page, lastPage, total, loading, busy, error, notice,
            currentUserId, load, search, changeRole, verify, resend, formatDate,
        };
    },
};
</script>

<style scoped>
.admin { max-width: 960px; }
.admin h1 { margin-bottom: 1rem; }
.muted { color: #888; font-size: 0.9rem; }
.search { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.search input { flex: 1; padding: 0.6rem 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
.btn-primary { padding: 0.6rem 1.25rem; background: #667eea; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
.panel { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 1rem 1.5rem; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th { text-align: left; padding: 0.6rem 0.5rem; color: #666; font-size: 0.85rem; border-bottom: 2px solid #eee; }
td { padding: 0.75rem 0.5rem; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
select { padding: 0.4rem 0.5rem; border: 1px solid #ddd; border-radius: 4px; }
.badge { display: inline-block; padding: 0.15rem 0.6rem; border-radius: 999px; font-size: 0.8rem; margin-right: 0.5rem; }
.badge.ok { background: #e8f8ee; color: #1e7e43; }
.badge.warn { background: #fff4e0; color: #a56200; }
.link-btn { background: none; border: none; color: #667eea; cursor: pointer; padding: 0 0.4rem; font-size: 0.9rem; }
.link-btn:disabled { color: #aaa; cursor: not-allowed; }
.pager { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; }
.error-message { margin-bottom: 1rem; padding: 0.75rem; background: #fee; color: #c33; border-radius: 4px; }
.success-message { margin-bottom: 1rem; padding: 0.75rem; background: #e8f8ee; color: #1e7e43; border-radius: 4px; }
</style>
