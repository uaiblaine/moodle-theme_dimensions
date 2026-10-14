# Never warn about the profile rule, so a site whose core setting opens every profile to everyone is told it is fine.
s/empty\(\$CFG->forceloginforprofiles \?\? 1\) \? recommendation::WARNING/false ? recommendation::WARNING/;
