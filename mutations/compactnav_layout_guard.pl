# Forget the layouts that draw no navbar.
s/if \(in_array\(\(string\) \$page->pagelayout, guestnav::EXCLUDED_LAYOUTS, true\)\) \{/if (false) {/;
