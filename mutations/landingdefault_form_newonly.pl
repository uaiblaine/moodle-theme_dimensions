# Drop the new-course guard of the form: an existing course's form shows the default over what it holds.
s/if \(!empty\(\$hook->formwrapper->get_course\(\)->id\)\) \{/if (false) {/;
