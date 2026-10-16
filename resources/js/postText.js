// The text each channel really receives. Keep in step with App\Support\PostText (PHP) so the counters match what is published.

export const X_FREE_LIMIT = 280;
export const X_PREMIUM_LIMIT = 25000;

// Hashtags without "#" or spaces, unique, letters/digits/underscore of any language
export const cleanHashtags = (tags) => {
    const clean = [];
    for (const raw of tags || []) {
        const tag = String(raw).trim().replace(/^#+/, '').replace(/[^\p{L}\p{N}_]+/gu, '');
        if (tag && tag.length <= 60 && !clean.some(t => t.toLowerCase() === tag.toLowerCase())) clean.push(tag);
    }
    return clean.slice(0, 30);
};

// "text\n\n#a #b" (just the hashtags when there is no text)
export const compose = (content, hashtags) => {
    const text = (content || '').trim();
    const line = cleanHashtags(hashtags).map(t => '#' + t).join(' ');
    return (text + (text && line ? '\n\n' : '') + line).trim();
};

// Counted like the server does: characters, not bytes (an emoji counts as one)
export const length = (text) => [...text].length;

// What a platform allows, or null when it has no limit we check ourselves
export const limitFor = (platform) => {
    switch (platform.platform_type) {
        case 'twitter': return platform.account_tier === 'premium' ? X_PREMIUM_LIMIT : X_FREE_LIMIT;
        case 'instagram': return 2200;
        case 'linkedin': return 3000;
        default: return null;
    }
};

// The final text for one channel: its own version / hashtags when it has them, else the shared ones
export const forPlatform = (content, hashtags, overrides, platformId) => {
    const own = (overrides || {})[platformId] || {};
    return compose(own.content ?? content, own.hashtags ?? hashtags);
};
