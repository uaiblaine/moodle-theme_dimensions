# Count the key under a chip of its own, which the toolbar does not have.
s/self::STATE_KEY => self::STATE_OPEN,/self::STATE_KEY => self::STATE_KEY,/;
