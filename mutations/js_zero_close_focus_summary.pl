# Give the focus back to the poster of a zeroed area as it closes. That poster has just left the
# grid for the summary, so the focus falls to the document's body instead of the summary it joined.
s/if \(focus && zeroed\) \{/if (focus && !zeroed) {/;
