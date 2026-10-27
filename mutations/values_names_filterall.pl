# Format a field's name once for every course even while the string filters run: a course whose filters differ
# reads the first course's spelling.
s/if \(!\\core\\di::get\(\\core\\formatting::class\)->get_filterall\(\)\) \{/if (true) {/;
