# Drop the disabled "Enrolment from dd/mm/yyyy" button: a course whose window opens later offers nothing to say so.
s/if \(\$openslabel !== ''\) \{/if (false) {/;
