# Keep a node core cloned twice: the same destination is listed twice.
s/if \(isset\(\$seen\[\$address\]\)\) \{/if (false) {/;
