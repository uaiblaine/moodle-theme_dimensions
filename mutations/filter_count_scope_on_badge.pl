# Count a scope value on the Filters badge, which the line's cells already show.
s/\+ \(\$this->state !== null && !self::is_scope\(\$this->state\) \? 1 : 0\);/+ (\$this->state !== null ? 1 : 0);/;
