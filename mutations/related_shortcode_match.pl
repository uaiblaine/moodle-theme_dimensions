# match="all" would match any.
s/self::text\(\$args, \x27match\x27\) === finder::MATCH_ALL \? finder::MATCH_ALL : finder::MATCH_ANY/finder::MATCH_ANY/;
