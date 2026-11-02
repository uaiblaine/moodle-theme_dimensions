# Show the footer anywhere on the page: core's hide on the way down never holds.
s/ \|\| document\.body\.clientWidth >= PHONE \|\| !nearEnd\(\)\) \{/ || document.body.clientWidth >= PHONE) {/;
