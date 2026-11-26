# A negative group reaches the session's choice, which writes $SESSION on a read-only path.
s/if \(\$groupid < 0\) \{/if (false) {/;
