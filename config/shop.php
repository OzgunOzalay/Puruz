<?php

return [
    // Mass-produced products with stock counts and variants ("Seri üretim"). Off while the
    // workshop only sells unique pieces; existing products of that type keep working.
    'mass_production' => (bool) env('SHOP_MASS_PRODUCTION', false),
];
