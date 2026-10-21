# Storefront redesign: mosaic + ink sketch

Date: 2026-10-20. Most visitors arrive on phones from Instagram ads, so the shop needed a look of its own and light motion.

## Direction

The brand's own material, taken from the @puruz.atolye photos: broken-tile mosaic (the mirror and its checkered edge, the tesserae close-up) and the ink sketch of the workshop building from the opening poster.

## What changed

- **Brand images** in `public/images/site/`:
  - `ayna.webp` is the default hero, used when the `hero_image` setting is empty.
  - `atolye.webp` and `tesserae.webp` are used for the story and the workshops header.
  - `bina.webp` is the poster's building sketch on a transparent background.
- **Tokens:**
  - colours `mint`, `ochre` and `plaster`;
  - `checker-edge` (the mirror's edge) and `shard-1..4` / `shard-hero` (uneven tile corners);
  - `fade-edge-r` for horizontal scrollers.
- **Motion**, all inside `prefers-reduced-motion: no-preference` and gated by `html.motion`:
  - `[data-reveal]` rise-in, and `[data-reveal="tile"]` tilt-and-settle for product cards;
  - `.draw-line` for marker underlines that draw themselves;
  - `.mask-draw` wipe for the sketch;
  - `.shard` mosaic tiles that drift in;
  - `fade-in` for images, `settle` for the hero, `slide-up` for the phone buy bar, a `pop` on the cart badge, a slow `spin-slow` on the sun, and `menu-stagger`.
  - The reveal JS is a single IntersectionObserver in `resources/js/app.js`. If it never runs, an inline head script removes `html.motion` after 2.5s, so content is never left hidden.
- **Home:**
  1. photo-first hero with drifting shards and word-by-word title;
  2. category row;
  3. tile cards;
  4. the new "atölyeden" story section (artist and tesserae collage);
  5. a mint workshops band;
  6. the new "bizi ziyaret edin" section with the sketch drawing itself, the address and a directions link.
- **Product page:** the first photo settles in, the rest fade in, the info column is revealed, the buy bar slides up, and a checker strip sits above the related pieces.
- **Other pages:**
  - workshops: a tesserae header band;
  - link page: the artist avatar and the building sketch;
  - layout: a checker strip at the top and above a plaster footer.

## Notes

- No admin or settings changes. The story copy is fixed in `shop/home.blade.php`.
- The visit address falls back to the poster address until `address` is set in Mağaza ayarları.
- The default Instagram handle is now `puruz.atolye`, the real account.
