# Draw the actions' wrapper for an event core allows neither Edit nor Delete.
s/if \(!edit\.edit && !edit\.delete\) \{/if (!edit.edit \&\& !edit.delete \&\& !item) {/;
