# Let the guest account through the one write of the showcase: the guest is "logged in" as far
# as core is concerned, so nothing else in the chain stops it writing a favourite onto the
# shared guest account.
s/if \(isguestuser\(\)\) \{/if (false) {/;
