import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Reverb settings come from <meta name="reverb-config"> (rendered by the server),
 * so the same asset build works locally and in production. Empty host/port/scheme
 * fall back to the current page: nginx proxies /app to Reverb on the same origin.
 */
const meta = document.querySelector('meta[name="reverb-config"]');

if (meta) {
    try {
        const config = JSON.parse(meta.content);
        const scheme = config.scheme || window.location.protocol.replace(':', '');
        const tls = scheme === 'https';
        const port = Number(config.port || window.location.port || (tls ? 443 : 80));

        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: config.host || window.location.hostname,
            wsPort: port,
            wssPort: port,
            forceTLS: tls,
            enabledTransports: ['ws', 'wss'],
        });
    } catch (error) {
        console.error('[echo] invalid reverb config', error);
    }
}
