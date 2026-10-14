# Drop the scheduled return from the resolver: a viewer enrolled from a later date is offered the
# open self instance, which would let them in past the date an administrator set.
s/if \(\$enrolment\['type'\] === access::RELATIONSHIP_SCHEDULED\) \{/if (false) {/;
