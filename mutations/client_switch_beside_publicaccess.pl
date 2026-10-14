# Fold the web service's switch into the gate of every public page.
s/if \(!self::enabled\(\)\) \{/if (!self::enabled() || !self::client_enabled()) {/;
