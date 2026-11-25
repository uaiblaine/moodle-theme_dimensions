# accessallgroups no longer lifts separate groups: the editing teacher is narrowed to their (no) groups.
s/ \|\| has_capability\('moodle\/site:accessallgroups', \$context\)\) \{/) {/;
