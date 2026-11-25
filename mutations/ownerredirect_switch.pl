# Ignore the theme's switch: an administrator who turned the redirect off still gets it.
s/if \(!\$enabled\) \{/if (false) {/;
