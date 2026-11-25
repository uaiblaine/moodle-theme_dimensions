# Drop the line for the rows older than the last month window: the cap hides their count.
s/if \(!\$last \|\| end\(\$windows\)\['start'\] > \(int\) \$last->timecreated\) \{/if (false) {/;
