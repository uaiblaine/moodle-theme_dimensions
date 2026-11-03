# The expected completion date is ignored and the course's end date closes every step.
s/\$expected > 0 \? \$expected : \(int\) \$ref->enddate/(int) \$ref->enddate/;
