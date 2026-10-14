# Stop putting a pair given backwards in order when a period arrives from outside the control: a
# hand-edited address with the two dates swapped then reaches a web service that refuses it, and
# the first fetch on that page is an error.
s/if \(first !== '' && last !== '' && last < first\) \{/if (false) {/;
