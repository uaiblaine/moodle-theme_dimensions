# Add the compact class whenever the hero is compact, so the compact layout carries it twice.
s/if \(\$compact && \$layout !== branding::LAYOUT_COMPACT\) \{/if (\$compact) {/;
