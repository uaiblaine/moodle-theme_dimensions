# Offer the colour mode control whatever core says: with colour modes off, or to somebody who cannot store the
# preference, the control would write a mode core then ignores.
s/if \(!class_exists\(colour_mode::class\) \|\| !colour_mode::can_choose_mode\(\)\) \{/if (!class_exists(colour_mode::class)) {/;
