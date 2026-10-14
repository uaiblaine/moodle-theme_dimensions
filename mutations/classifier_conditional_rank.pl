# Rank a conditional enrolment above every offer: it beats an open route.
s/self::STATE_CONDITIONAL => 1,/self::STATE_CONDITIONAL => 9,/;
