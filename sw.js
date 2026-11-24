// Service Worker (works at both /sw.js and /society_app/sw.js)
const BASE_PATH = self.location.pathname.replace(/\/sw\.js$/, '');

self.addEventListener('push', function (event) {
    let data = {
        title: 'Visitor at Gate!',
        body: 'A visitor is waiting for approval.',
        icon: `${BASE_PATH}/assets/icon-192.svg`,
        visitor_id: null,
        action_token: '',
        url: `${BASE_PATH}/resident/dashboard`
    };

    if (event.data) {
        try {
            const parsed = event.data.json();
            data = Object.assign(data, parsed);
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: data.icon || `${BASE_PATH}/assets/icon-192.svg`,
        badge: `${BASE_PATH}/assets/icon-192.svg`,
        vibrate: [500, 250, 500, 250, 500],
        requireInteraction: true,
        data: {
            visitor_id: data.visitor_id,
            action_token: data.action_token || '',
            url: data.url || `${BASE_PATH}/resident/dashboard`
        },
        actions: [
            { action: 'approve', title: 'Approve' },
            { action: 'deny', title: 'Deny' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const data = event.notification.data || {};

    if (event.action === 'approve' || event.action === 'deny') {
        const newStatus = event.action === 'approve' ? 'Entered' : 'Denied';

        event.waitUntil(
            fetch(`${BASE_PATH}/api/update_visitor_status.php`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    visitor_id: data.visitor_id,
                    status: newStatus,
                    action_token: data.action_token || ''
                })
            })
            .then(async (response) => {
                let result = {};
                try { result = await response.json(); } catch (e) {}
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to update visitor status.');
                }
                return self.registration.showNotification('Status Updated', {
                    body: newStatus === 'Entered' ? 'Visitor approved for entry.' : 'Visitor entry denied.',
                    icon: `${BASE_PATH}/assets/icon-192.svg`,
                    vibrate: [200]
                });
            })
            .catch(() => {
                return self.registration.showNotification('Action Needed', {
                    body: 'Please open the Resident Dashboard to handle this visitor.',
                    icon: `${BASE_PATH}/assets/icon-192.svg`,
                    data: { url: data.url || `${BASE_PATH}/resident/dashboard` }
                });
            })
        );
    } else {
        event.waitUntil(
            clients.openWindow(data.url || `${BASE_PATH}/resident/dashboard`)
        );
    }
});
