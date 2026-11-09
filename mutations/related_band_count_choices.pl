# A stored count outside the choices would be taken as it is.
s/isset\(self::choices\(\)\[\$count\]\) \? \$count/\$count > 0 ? \$count/;
