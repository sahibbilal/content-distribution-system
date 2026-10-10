import { ref } from 'vue';
import axios from 'axios';

// Public site content (home page text, plans, payment methods, contact email), shared by all pages
export const siteContent = ref(null);

let pending = null;

export function loadSiteContent(force = false) {
    if (force || !pending) {
        pending = axios.get('/site-content')
            .then(response => {
                siteContent.value = response.data;
                return response.data;
            })
            .catch(error => {
                pending = null;
                throw error;
            });
    }
    return pending;
}

export function setSiteContent(data) {
    siteContent.value = data;
    pending = Promise.resolve(data);
}
