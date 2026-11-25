# Send an owner the overview refuses (no local/dimensions:view) to its error page.
s/if \(!has_capability\(self::CAPABILITY, \\core\\context\\system::instance\(\), \$userid\)\) \{/if (false) {/;
