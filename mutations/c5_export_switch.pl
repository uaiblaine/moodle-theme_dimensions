# Offer the export with the site's switch off.
s/if \(empty\(\$CFG->enablecalendarexport\) \|\| !self::create\(\)\) \{/if (!self::create()) {/;
