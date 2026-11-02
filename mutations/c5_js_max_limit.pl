# Allow a day after core's latest.
s/if \(edit\.maxday !== '' && day > edit\.maxday\) \{/if (edit.maxday !== '' \&\& day > edit.maxday \&\& !day) {/;
