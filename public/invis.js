/* global turnstile */
window._mouseMoves = window._mouseMoves || 0;
window._keyPress = window._keyPress || 0;

if (!window.__dominserviceInvisTrackingInitialized) {
    window.__dominserviceInvisTrackingInitialized = true;

    const scheduleRefresh = function () {
        clearTimeout(window.__dominserviceInvisRefreshTimeout);
        window.__dominserviceInvisRefreshTimeout = window.setTimeout(function () {
            if (typeof window.invisCaptcha === 'function') {
                window.invisCaptcha().catch(function () {});
            }
        }, 250);
    };

    const incrementMouseMoves = function (amount = 1) {
        window._mouseMoves = (window._mouseMoves || 0) + amount;
        scheduleRefresh();
    };

    const incrementKeyPress = function () {
        window._keyPress = (window._keyPress || 0) + 1;
        scheduleRefresh();
    };

    window.addEventListener('mousemove', function () { incrementMouseMoves(1); }, { passive: true });
    window.addEventListener('mousedown', function () { incrementMouseMoves(3); }, { passive: true });
    window.addEventListener('click', function () { incrementMouseMoves(3); }, { passive: true });
    window.addEventListener('touchstart', function () { incrementMouseMoves(3); }, { passive: true });
    window.addEventListener('keydown', incrementKeyPress, { passive: true });
    window.addEventListener('input', incrementKeyPress, { passive: true });
}

// Define the main function that can be called at any time
window.invisCaptcha = async function(customConfig = {}) {
    // Get config from script tag, window.invisConfig (for Livewire), or passed customConfig
    const C = JSON.parse(
        (document.currentScript && document.currentScript.dataset.cfg) ||
        window.invisConfig ||
        '{}'
    );

    // Merge with any custom config passed to the function
    Object.assign(C, customConfig);

    if (window.DominserviceFingerprintTracking && window.DominserviceFingerprintTracking.state?.ready) {
        try {
            await window.DominserviceFingerprintTracking.state.ready;
        } catch (e) {}
    }

    const forms = document.querySelectorAll('form[data-invis]');

    /* --------------- zbieranie sygnałów --------------- */
    await new Promise(r => setTimeout(r, 400));           // zbierz ruch użytk.
    const signals = {
        ts  : Date.now(),
        ua  : navigator.userAgent,
        lang: navigator.language,
        dpr : window.devicePixelRatio,
        cpu : navigator.hardwareConcurrency || null,
        wd  : navigator.webdriver,
        mm  : window._mouseMoves || 0,
        kb  : window._keyPress   || 0,
    };

    if (window.DominserviceFingerprintTracking && window.DominserviceFingerprintTracking.state) {
        signals.fingerprint = window.DominserviceFingerprintTracking.state.fingerprint || null;
        signals.tracking_event_ulid = window.DominserviceFingerprintTracking.state.trackingEventUlid || null;
    }

    window.dispatchEvent(new CustomEvent('dominservice:invis:before-token', {
        detail: { signals }
    }));

    /* Polyfill-Poisoning */
    if (C.polyfill_poison?.enabled) {
        C.polyfill_poison.targets.forEach(t => {
            const [obj, prop] = t.split('.prototype.');
            if (window[obj] && window[obj].prototype[prop])
                window[obj].prototype[prop] = () => '';
        });
    }

    /* --------------- pobranie tokenu --------------- */
    const res   = await fetch('/invis-captcha/token', {
        method : 'POST',
        headers: {'Content-Type':'application/json'},
        body   : JSON.stringify(signals),
        credentials:'same-origin'
    }).then(r => r.json());
    const {token, score} = res;

    /* --------------- Turnstile fallback --------------- */
    if (C.turnstile?.enabled && score < C.turnstile.fallback) {
        await new Promise(ok=>{
            const s=document.createElement('script');
            s.src='https://challenges.cloudflare.com/turnstile/v0/api.js';
            s.onload=ok; document.head.appendChild(s);
        });
        forms.forEach(f=>{
            const d=document.createElement('div');
            d.className='cf-turnstile';
            d.dataset.sitekey=C.turnstile.sitekey;
            d.dataset.callback='_tsOK';
            f.appendChild(d);
        });
        window._tsOK=t=>{
            forms
                .forEach(f=>f.insertAdjacentHTML('beforeend',
                    `<input type="hidden" name="turnstile_token" value="${t}">`));
        };
        return;
    }

    /* --------------- dynamiczne pola --------------- */
    if (C.dynamic_fields.enabled) {
        const fieldMappings = {};

        forms.forEach(f=>{
            [...f.elements].forEach(el=>{
                const pref = C.dynamic_fields.prefixes
                    .find(p=>el.name===p && !el.dataset.dyn);
                if (pref) {
                    const newName = pref + '_' +
                        [...crypto.getRandomValues(new Uint8Array(C.dynamic_fields.length))]
                            .map(b=>('0'+(b%36).toString(36)).slice(-1)).join('');
                    el.dataset.dyn = el.name;           // zapisz oryginał
                    el.name = newName;

                    // Add to mappings
                    fieldMappings[pref] = newName;
                }
            });
        });

        // Send mappings to server if we have any
        if (Object.keys(fieldMappings).length > 0) {
            fetch('/invis-captcha/field-map', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({mappings: fieldMappings}),
                credentials: 'same-origin'
            }).catch(err => console.error('Failed to save field mappings:', err));
        }
    }

    /* --------------- wstrzyknięcie tokenu --------------- */
    forms.forEach(f=>{
        let h=f.querySelector('input[name="invis_token"]');
        if(!h){h=document.createElement('input');h.type='hidden';h.name='invis_token';f.appendChild(h);}
        h.value=token;

        if (signals.fingerprint) {
            let fp=f.querySelector('input[name="fingerprint"]');
            if(!fp){fp=document.createElement('input');fp.type='hidden';fp.name='fingerprint';f.appendChild(fp);}
            fp.value=signals.fingerprint;
        }

        if (signals.tracking_event_ulid) {
            let te=f.querySelector('input[name="tracking_event_ulid"]');
            if(!te){te=document.createElement('input');te.type='hidden';te.name='tracking_event_ulid';f.appendChild(te);}
            te.value=signals.tracking_event_ulid;
        }
    });

    return { token, score };
};

// Auto-execute the function when the script is loaded
(async () => {
    await window.invisCaptcha();
})();
