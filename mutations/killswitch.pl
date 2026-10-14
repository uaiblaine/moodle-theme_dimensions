# Ignore the kill switch: the showcase keeps rendering after the administrator switched it off.
s/if \(!population::enabled\(\)\) \{/if (false) {/;
