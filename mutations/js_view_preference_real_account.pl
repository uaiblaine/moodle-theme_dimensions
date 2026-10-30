# Write the view preference whatever the account: the guest's write is refused with an exception dialogue on every
# view change.
s/if \(kind !== 'view' \|\| !canpersist\) \{/if (kind !== 'view') {/;
