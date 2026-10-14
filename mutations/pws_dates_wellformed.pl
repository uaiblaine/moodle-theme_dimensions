# Accept a date that is not YYYY-MM-DD, which criteria then drops in silence.
s/if \(\$params\[\$bound\] !== '' && criteria::parse_date\(\(string\) \$params\[\$bound\]\) === ''\) \{/if (false) {/;
