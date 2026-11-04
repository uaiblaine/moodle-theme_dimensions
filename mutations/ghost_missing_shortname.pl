# Leave an unknown shortname to core: shortnames could be probed where ids cannot.
s/return self::may_ghost\(/return false && self::may_ghost(/;
