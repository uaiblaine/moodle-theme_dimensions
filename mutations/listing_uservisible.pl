# Drop core's visibility gate from the page: a hidden category reaches the population and is refused with
# the theme's exception, where core's own page answers with its own - a different message for the same id.
s/if \(\$coursecat === null \|\| !\$coursecat->is_uservisible\(\)\) \{/if (\$coursecat === null) {/;
