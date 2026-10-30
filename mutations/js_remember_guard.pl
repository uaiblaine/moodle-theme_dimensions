# Remember for the guest's and the root's pages too (the guard on the account and the root dropped).
s/if \(!canpersist \|\| isRootPage\(\)\) \{/if (false) {/;
