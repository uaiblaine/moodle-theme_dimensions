# The batch keeps a group whose visibility is none for everybody: a learner's feed gets its events.
s/if \(\(int\) \$row->visibility === GROUPS_VISIBILITY_NONE\) \{/if (false) {/;
