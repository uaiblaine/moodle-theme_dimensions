# Offer the export to an account core's calendar offers it to no more.
s/if \(empty\(\$CFG->enablecalendarexport\) \|\| !self::create\(\)\) \{/if (empty(\$CFG->enablecalendarexport)) {/;
