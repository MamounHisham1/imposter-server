function generateId() {
    return crypto.randomUUID();
}

function setCookie(name, value, days) {
    const expires = new Date(Date.now() + days * 86400000).toUTCString();
    document.cookie = `${name}=${value}; expires=${expires}; path=/; SameSite=Lax`;
}

function getCookie(name) {
    return document.cookie.split('; ').find(row => row.startsWith(name + '='))?.split('=')[1] || null;
}

export function initTracking() {
    // Visitor ID: persists for 365 days (across sessions)
    let visitorId = getCookie('imp_vid');
    if (!visitorId) {
        visitorId = generateId();
        setCookie('imp_vid', visitorId, 365);
    }

    // Session ID: persists for 30 minutes of inactivity (refreshed on each page view)
    let sessionId = getCookie('imp_sid');
    if (!sessionId) {
        sessionId = generateId();
    }
    setCookie('imp_sid', sessionId, 1); // 1 day max, but we refresh it
}
