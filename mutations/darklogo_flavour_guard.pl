# Ignore the flavour: the site's dark file is painted over a flavour's own logo.
s/if \(\$flavour !== null && !empty\(\$flavour->\{self::FLAVOUR_FIELD\[\$variant\]\}\)\) \{/if (false) {/;
