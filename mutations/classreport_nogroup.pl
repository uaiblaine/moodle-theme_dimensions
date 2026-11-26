# A separate-groups grader in no group: the empty list reaches core's join, where it means every group.
s/if \(\$groups === \[\]\) \{/if (false) {/;
