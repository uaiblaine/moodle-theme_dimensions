# Take a refusal of the route (a 404, a 5xx) as a half.
s/if \(!response\.ok\) \{/if (false) {/;
