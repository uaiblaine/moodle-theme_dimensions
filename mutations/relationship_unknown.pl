# Take any type the plugin answers, known or not.
s/return isset\(self::RELATIONSHIP_RANK\[\$type\]\) \? \$type : access::RELATIONSHIP_NONE;/return \$type;/;
