# Offer "Create account" on a site that takes no sign-ups: the button leads to core's refusal.
s/if \(!signup_is_enabled\(\)\) \{/if (false) {/;
