# Hide the navbar on the Behat site too: Selenium's clicks into a bar moved off the screen fail.
s/if \(\$behatsite \?\? self::behat_site\(\)\) \{/if (false) {/;
