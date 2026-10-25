# Never draw the phone's entries: the module finds no holder and the navbar keeps every control.
s/if \(\$phoneentries\) \{/if (false) {/;
