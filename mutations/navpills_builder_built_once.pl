# Build the category navigation on every call: a page asked twice has core report its nodes as added twice.
s/if \(!isset\(\$navs\[\$contextid\]\)\) \{/if (true) {/;
