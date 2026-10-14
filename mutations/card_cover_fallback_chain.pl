# Take the first link off the card's still: the cover a manager uploaded for the CARD is never
# reached, so a category that has one shows the hero's background instead and a category that has
# only a cover shows the bare colour band. Nothing errors and every card still looks deliberate.
s/if \(\$branding\['cover'\] !== null\) \{/if (false) {/;
