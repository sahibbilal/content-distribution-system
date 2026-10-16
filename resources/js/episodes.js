// Labels and colours for the episode states (see the state machine in 01-webapp-updates.md)
const LABELS = {
    pending: 'Waiting to start',
    researching: 'Researching',
    awaiting_approval: 'Needs your approval',
    approved: 'Approved',
    voicing: 'Making voice',
    awaiting_avatar: 'Needs avatar clip',
    editing: 'Editing video',
    importing: 'Importing videos',
    ready: 'Videos ready: approve',
    scheduling: 'Scheduling',
    scheduled: 'Scheduled',
    published: 'Published',
    failed: 'Failed',
    rejected: 'Rejected',
    skipped: 'Skipped',
    cancelled: 'Cancelled',
};

export const statusLabel = (status) => LABELS[status] || status;

export const statusBadge = (status) => {
    if (['published', 'scheduled'].includes(status)) return 'badge-success';
    if (['awaiting_approval', 'awaiting_avatar', 'ready'].includes(status)) return 'badge-warning';
    if (['failed', 'skipped'].includes(status)) return 'badge-danger';
    return 'badge-neutral';
};

// Statuses where the worker or a queued job is busy, so the page keeps refreshing
export const BUSY = ['pending', 'researching', 'approved', 'voicing', 'editing', 'importing', 'scheduling'];

export const kindLabel = (kind) => ({ video: 'Video', text: 'Text post', image: 'Text + image' })[kind] || kind;
