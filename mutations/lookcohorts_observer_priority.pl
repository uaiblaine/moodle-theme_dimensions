# The deletion observer runs after Boost Union's, which has already stripped the cohort from every rule.
s/'priority' => 1000,/'priority' => -1,/;
