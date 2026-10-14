# Lift the core clamp for everybody: the courses of a category core hides from this viewer are
# listed as if the category were open, instead of only the ones they are enrolled in.
s/if \(\(\$clamp & scope::CLAMP_CORE\) && !\$active\) \{/if (false) {/;
