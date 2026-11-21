# The batch drops a hidden group even for a teacher who may see hidden groups.
s/if \(!\$viewhidden\[\$courseid\]\) \{/if (true) {/;
