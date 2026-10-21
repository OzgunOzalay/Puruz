import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

// Swipeable product gallery: tracks which slide is visible so the dots can follow.
Alpine.data('gallery', (count) => ({
    count,
    active: 0,
    onScroll(el) {
        this.active = Math.round(el.scrollLeft / el.clientWidth);
    },
    go(index) {
        const track = this.$refs.track;
        track.scrollTo({ left: index * track.clientWidth, behavior: 'smooth' });
    },
}));

// Province → district selects on the checkout page.
Alpine.data('addressPicker', (districts, city = '', district = '') => ({
    districts,
    city,
    district,
    get cityDistricts() {
        return this.districts[this.city] ?? [];
    },
}));

// Copies a link and shows a short confirmation (used on the link-in-bio and order pages).
Alpine.data('copyLink', (text) => ({
    copied: false,
    async copy() {
        try {
            await navigator.clipboard.writeText(text);
            this.copied = true;
            setTimeout(() => (this.copied = false), 1800);
        } catch (e) {
            window.prompt('Bağlantıyı kopyalayın:', text);
        }
    },
}));

// Scroll reveals: each element gets .is-in once, the first time it comes into view. Elements that
// arrive together are staggered by --i, unless the template already set one (e.g. hero words).
function setupReveals() {
    const elements = document.querySelectorAll('[data-reveal], .draw-line, .mask-draw');
    if (!('IntersectionObserver' in window)) {
        elements.forEach((el) => el.classList.add('is-in'));
        return;
    }
    const observer = new IntersectionObserver(
        (entries) => {
            let batch = 0;
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                const el = entry.target;
                if (el.hasAttribute('data-reveal') && !el.style.getPropertyValue('--i')) {
                    el.style.setProperty('--i', Math.min(batch++, 6));
                }
                el.classList.add('is-in');
                observer.unobserve(el);
            }
        },
        { rootMargin: '0px 0px -6% 0px', threshold: 0.12 },
    );
    elements.forEach((el) => observer.observe(el));
}

// Lazy photos fade in once loaded instead of popping in.
function setupImageFades() {
    document.querySelectorAll('img.fade-in').forEach((img) => {
        const done = () => img.classList.add('loaded');
        if (img.complete) done();
        else {
            img.addEventListener('load', done, { once: true });
            img.addEventListener('error', done, { once: true });
        }
    });
}

setupReveals();
setupImageFades();
window.__motionReady = true;

window.Alpine = Alpine;
Alpine.start();
