import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'pusher'>;
    }
}

export function getEchoInstance(): Echo<'pusher'> | null {
    if (typeof window === 'undefined') return null;
    if (window.Echo) return window.Echo;

    const key = import.meta.env.VITE_PUSHER_APP_KEY;
    const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';

    if (!key) return null;

    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key,
        cluster,
        forceTLS: true,
    });

    return window.Echo;
}
