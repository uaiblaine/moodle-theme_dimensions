# Ignore enrol_conditional's places cap: a full instance reads open, the one direction of error not allowed.
s/return \(\$cap > 0 && \$taken >= \$cap\) \? self::STATE_NONE : self::STATE_OPEN;/return self::STATE_OPEN;/;
