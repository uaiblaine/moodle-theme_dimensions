# Progress is read for any account, enrolled in the trail or not.
s/if \(\$entries && !empty\(\$enrolments\[\$courseid\]->active\)\) \{/if (\$entries) {/;
