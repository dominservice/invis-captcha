/* global turnstile */
window._mouseMoves = window._mouseMoves || 0;
window._keyPress = window._keyPress || 0;
window.__dominserviceInvisRawConfig = window.__dominserviceInvisRawConfig || null;

function dominserviceInvisGetRawConfig() {
    if (window.__dominserviceInvisRawConfig) {
        return window.__dominserviceInvisRawConfig;
    }

    if (document.currentScript && document.currentScript.dataset && document.currentScript.dataset.cfg) {
        window.__dominserviceInvisRawConfig = document.currentScript.dataset.cfg;
        return window.__dominserviceInvisRawConfig;
    }

    const script = document.querySelector('script[src*="vendor/invis-captcha/invis.js"][data-cfg]');

    if (script && script.dataset && script.dataset.cfg) {
        window.__dominserviceInvisRawConfig = script.dataset.cfg;
        return window.__dominserviceInvisRawConfig;
    }

    return window.invisConfig || '{}';
}

function dominserviceInvisDebug(eventName, context) {
    let config;

    try {
        config = JSON.parse(dominserviceInvisGetRawConfig());
    } catch (error) {
        return;
    }

    if (!config.debug || !config.debug.enabled || !config.debug.endpoint) {
        return;
    }

    const payload = JSON.stringify({
        event: eventName,
        context: context || {},
        url: window.location.href
    });

    try {
        if (navigator.sendBeacon) {
            navigator.sendBeacon(config.debug.endpoint, new Blob([payload], { type: 'application/json' }));
            return;
        }

        fetch(config.debug.endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: payload,
            credentials: 'same-origin',
            keepalive: true
        }).catch(function () {});
    } catch (error) {}
}

if (!window.__dominserviceInvisTrackingInitialized) {
    window.__dominserviceInvisTrackingInitialized = true;
    dominserviceInvisDebug('tracking_init', {});

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

if (!window.__dominserviceInvisSubmitGuardInitialized) {
    window.__dominserviceInvisSubmitGuardInitialized = true;
    dominserviceInvisDebug('submit_guard_init', {});

    const refreshTokenBeforeSubmit = async function () {
        if (typeof window.invisCaptcha !== 'function') {
            return;
        }

        await window.invisCaptcha();
    };

    document.addEventListener('click', function (event) {
        const submitter = event.target instanceof Element
            ? event.target.closest('button[type="submit"], input[type="submit"]')
            : null;

        if (!submitter || submitter.dataset.invisBypass === '1') {
            return;
        }

        const form = submitter.form || submitter.closest('form');

        if (!form || !form.matches('form[data-invis]')) {
            return;
        }

        dominserviceInvisDebug('click_submit_intercepted', {
            formId: form.id || null,
            submitterId: submitter.id || null
        });

        event.preventDefault();
        event.stopImmediatePropagation();

        refreshTokenBeforeSubmit()
            .then(function () {
                submitter.dataset.invisBypass = '1';
                submitter.click();
            })
            .finally(function () {
                window.setTimeout(function () {
                    delete submitter.dataset.invisBypass;
                }, 0);
            });
    }, true);

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('form[data-invis]')) {
            return;
        }

        if (form.dataset.invisSubmitBypass === '1') {
            return;
        }

        const submitter = event.submitter || null;

        if (submitter && submitter.dataset && submitter.dataset.invisBypass === '1') {
            return;
        }

        dominserviceInvisDebug('form_submit_intercepted', {
            formId: form.id || null,
            submitterId: submitter && submitter.id ? submitter.id : null
        });

        event.preventDefault();
        event.stopImmediatePropagation();

        refreshTokenBeforeSubmit()
            .then(function () {
                form.dataset.invisSubmitBypass = '1';

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter || undefined);
                } else {
                    form.submit();
                }
            })
            .finally(function () {
                window.setTimeout(function () {
                    delete form.dataset.invisSubmitBypass;
                }, 0);
            });
    }, true);
}

// Define the main function that can be called at any time
window.invisCaptcha = async function(customConfig = {}) {
    // Get config from script tag, window.invisConfig (for Livewire), or passed customConfig
    const C = JSON.parse(
        dominserviceInvisGetRawConfig()
    );

    // Merge with any custom config passed to the function
    Object.assign(C, customConfig);

    if (window.DominserviceFingerprintTracking && window.DominserviceFingerprintTracking.state?.ready) {
        try {
            await window.DominserviceFingerprintTracking.state.ready;
        } catch (e) {}
    }

    const forms = document.querySelectorAll('form[data-invis]');
    dominserviceInvisDebug('invis_start', { formsCount: forms.length });

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
    dominserviceInvisDebug('token_request_start', {
        mm: signals.mm,
        kb: signals.kb,
        wd: signals.wd
    });

    const response = await fetch('/invis-captcha/token', {
        method : 'POST',
        headers: {'Content-Type':'application/json'},
        body   : JSON.stringify(signals),
        credentials:'same-origin'
    });
    const res = await response.json();
    dominserviceInvisDebug('token_request_done', {
        ok: response.ok,
        status: response.status,
        hasToken: !!res.token,
        score: res.score ?? null
    });
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
    if (C.dynamic_fields && C.dynamic_fields.enabled) {
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

    dominserviceInvisDebug('token_injected', {
        formsCount: forms.length,
        hasToken: !!token,
        score: score
    });

    return { token, score };
};

// Auto-execute the function when the script is loaded
(async () => {
    try {
        dominserviceInvisDebug('bootstrap_start', {});
        await window.invisCaptcha();
        dominserviceInvisDebug('bootstrap_done', {});
    } catch (error) {
        dominserviceInvisDebug('bootstrap_failed', {
            message: error && error.message ? error.message : String(error)
        });
    }
})();
