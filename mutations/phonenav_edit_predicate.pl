# Offer the edit mode entry to a reader core lets edit nothing.
s/if \(\$page->user_allowed_editing\(\)\) \{/if (true) {/;
