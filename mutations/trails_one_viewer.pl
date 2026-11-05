# A scope built for another viewer is answered as the current user's.
s/if \(\$scope->viewerid !== \(int\) \$USER->id\) \{/if (false) {/;
