# Count the waiting list under a chip of its own, which the toolbar does not have.
s/self::STATE_WAITLISTED => self::STATE_PENDING,/self::STATE_WAITLISTED => self::STATE_WAITLISTED,/;
