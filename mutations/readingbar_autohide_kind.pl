# Hide the navbar on every reading page, whatever the administrator ticked (off by default).
s/if \(!in_array\(self::kind\(\$page\), self::autohide_kinds\(\), true\)\) \{/if (false) {/;
