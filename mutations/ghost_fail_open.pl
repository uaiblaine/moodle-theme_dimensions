# Make a missing predicate ghost everybody: every enrolment page then dead-ends
# the moment the dependency is absent.
s/never fail a page over it\.\n            return false;/never fail a page over it.\n            return true;/;
