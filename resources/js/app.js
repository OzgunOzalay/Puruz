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

window.Alpine = Alpine;
Alpine.start();
