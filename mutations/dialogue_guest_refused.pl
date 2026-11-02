# Let the guest account through the dialogue's service: every account's service refuses it, this one would answer.
s/if \(isguestuser\(\)\) \{/if (false) {/;
