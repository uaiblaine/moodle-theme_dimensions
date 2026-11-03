# A course in a category the visitor cannot see is drawn to them as a ghost.
s/if \(empty\(\$categories\[\$categoryid\]\) \|\| !\$categories\[\$categoryid\]->visible\) \{/if (empty(\$categories[\$categoryid])) {/;
