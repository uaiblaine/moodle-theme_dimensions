# Offer the appearance switch whatever core says: with colour modes off, or to somebody who cannot store the
# preference, the switch would write a mode core then ignores.
s/if \(!class_exists\(colour_mode::class\) \|\| !colour_mode::can_choose_mode\(\)\) \{/if (!class_exists(colour_mode::class)) {/;
