// Display names for platform_type values returned by the API
const LABELS = { linkedin: 'LinkedIn', facebook: 'Facebook', youtube: 'YouTube', tiktok: 'TikTok' };

export const platformLabel = (type) => LABELS[type] || (type ? type.charAt(0).toUpperCase() + type.slice(1) : '');
