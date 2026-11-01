# Count suspended enrolments among an event course's participants.
s/ue\.status = :rowactive/ue.status >= :rowactive/;
