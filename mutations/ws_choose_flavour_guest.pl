# The service lets the guest through to choose(), which then answers a different refusal.
s/if \(isguestuser\(\)\) \{/if (false) {/;
