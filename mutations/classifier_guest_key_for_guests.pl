# Withhold the key from the guest account, as stage 0's interim mapping did.
s/\? self::STATE_FREE : self::STATE_KEY;/? self::STATE_FREE : (\$canenrol ? self::STATE_KEY : self::STATE_NONE);/;
