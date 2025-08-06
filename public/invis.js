/* global turnstile */
(async () => {
    const C = JSON.parse(document.currentScript.dataset.cfg || '{}');

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
    if (C.turnstile.enabled && score < C.turnstile.fallback) {
        await new Promise(ok=>{
            const s=document.createElement('script');
            s.src='https://challenges.cloudflare.com/turnstile/v0/api.js';
            s.onload=ok; document.head.appendChild(s);
        });
        document.querySelectorAll('form[data-invis]').forEach(f=>{
            const d=document.createElement('div');
            d.className='cf-turnstile';
            d.dataset.sitekey=C.turnstile.sitekey;
            d.dataset.callback='_tsOK';
            f.appendChild(d);
        });
        window._tsOK=t=>{
            document.querySelectorAll('form[data-invis]')
                .forEach(f=>f.insertAdjacentHTML('beforeend',
                    `<input type="hidden" name="turnstile_token" value="${t}">`));
        };
        return;
    }

    /* --------------- dynamiczne pola --------------- */
    if (C.dynamic_fields.enabled) {
        document.querySelectorAll('form[data-invis]').forEach(f=>{
            [...f.elements].forEach(el=>{
                const pref = C.dynamic_fields.prefixes
                    .find(p=>el.name===p && !el.dataset.dyn);
                if (pref) {
                    const newName = pref + '_' +
                        [...crypto.getRandomValues(new Uint8Array(C.dynamic_fields.length))]
                            .map(b=>('0'+(b%36).toString(36)).slice(-1)).join('');
                    el.dataset.dyn = el.name;           // zapisz oryginał
                    el.name = newName;
                }
            });
        });
    }

    /* --------------- wstrzyknięcie tokenu --------------- */
    document.querySelectorAll('form[data-invis]').forEach(f=>{
        let h=f.querySelector('input[name="invis_token"]');
        if(!h){h=document.createElement('input');h.type='hidden';h.name='invis_token';f.appendChild(h);}
        h.value=token;
    });
})();
