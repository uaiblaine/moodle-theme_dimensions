# Lift the plugin clamp: the listed courses of an unlisted category are shown to a viewer with
# no relationship to them.
s/if \(\(\$clamp & scope::CLAMP_PLUGIN\) && !\$related\) \{/if (false) {/;
