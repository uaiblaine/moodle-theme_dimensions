# Allow a day before core's earliest.
s/if \(edit\.minday !== '' && day < edit\.minday\) \{/if (edit.minday !== '' \&\& day < edit.minday \&\& !day) {/;
