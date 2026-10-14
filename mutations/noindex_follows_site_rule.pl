# Mark every public page noindex whatever the site's rule says: a site open to search engines then
# publishes pages no search engine may list.
s/if \(empty\(\$CFG->opentowebcrawlers\)\) \{/if (true) {/;
