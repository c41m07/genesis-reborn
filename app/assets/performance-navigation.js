// Envoi borné, sans URL inspectée ni cookie et sans faire attendre le chargement.
function collectNavigation() {
    const nav = performance.getEntriesByType('navigation')[0];
    const token = nav?.serverTiming?.find((entry) => entry.name === 'grperf')?.description;
    if (!token || document.visibilityState === 'hidden' || !Number.isFinite(nav.responseStart)) {
        return;
    }

    let lcp = null;
    let observer = null;
    const takeEntries = (entries) => {
        const last = entries.at(-1);
        if (last && Number.isFinite(last.startTime)) {
            lcp = last.startTime;
        }
    };
    if (window.PerformanceObserver?.supportedEntryTypes?.includes('largest-contentful-paint')) {
        observer = new PerformanceObserver((list) => takeEntries(list.getEntries()));
        observer.observe({ type: 'largest-contentful-paint', buffered: true });
    }
    let reported = false;
    let fallback = null;
    const report = () => {
        if (reported) return;
        reported = true;
        if (fallback !== null) window.clearTimeout(fallback);
        if (observer) {
            takeEntries(observer.takeRecords());
            observer.disconnect();
        }
        document.removeEventListener('visibilitychange', onVisibility);
        window.removeEventListener('pagehide', report);

        const data = {
            sample: token,
            device: window.matchMedia('(max-width: 767px)').matches ? 'mobile' : 'desktop',
        };
        if (nav.responseStart > 0) data.ttfb = Math.round(nav.responseStart);
        if (nav.responseEnd > nav.responseStart && nav.responseStart > 0) {
            data.html_transfer = Math.round(nav.responseEnd - nav.responseStart);
        }
        if (lcp !== null && lcp > 0) data.lcp = Math.round(lcp);
        if (!('ttfb' in data) && !('lcp' in data)) return;

        void fetch('/_performance/navigation', {
            method: 'POST',
            credentials: 'omit',
            referrerPolicy: 'no-referrer',
            keepalive: true,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        }).catch(() => {});
    };
    const onVisibility = () => { if (document.visibilityState === 'hidden') report(); };
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('pagehide', report, { once: true });
    // Les onglets gardés ouverts ne retardent pas indéfiniment le relevé.
    fallback = window.setTimeout(report, 10000);
}

if (typeof performance?.getEntriesByType === 'function') collectNavigation();
