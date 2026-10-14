# Drop the logged-in redirect, so a logged-in viewer - the guest account included - is
# answered by the anonymous shell instead of by the showcase their rights are read on.
s/if \(isloggedin\(\)\) \{/if (false) {/;
