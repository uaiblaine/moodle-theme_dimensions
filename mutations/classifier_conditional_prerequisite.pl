# Promise the conditional enrolment to anybody, not only to a viewer working towards the prerequisite.
s/return \$prerequisite \? self::STATE_CONDITIONAL : self::STATE_NONE;/return self::STATE_CONDITIONAL;/;
