# Forget that an entry from before the toggle held the favourites as its state chip: it is then read
# as no state at all, and the bare address opens on everything instead of the reader's favourites.
s/ \|\| \$state === classifier::CHIP_FAVOURITES\) \{/) {/;
