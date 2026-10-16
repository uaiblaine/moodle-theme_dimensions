# Read an older address's favourites chip as a chip again: it is then dropped as outside the
# vocabulary, and a bookmark or a shared link that opened on the reader's favourites opens on everything.
s/if \(\$state === classifier::CHIP_FAVOURITES\) \{/if (false) {/;
