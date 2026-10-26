# Show the footer anywhere on the page: core's hide on the way down never holds.
s/if \(!enabled \|\| document\.body\.clientWidth >= PHONE \|\| !nearEnd\(\)\) \{/if (!enabled || document.body.clientWidth >= PHONE) {/;
