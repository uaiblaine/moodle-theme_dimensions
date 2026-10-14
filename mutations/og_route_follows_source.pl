# Let the og area keep serving the course image while the tags name the share picture: the address
# the page printed answers 404.
s/\(\$filearea === self::AREA_OGIMAGE\) \? self::og_source\(\$course\) : self::hero_file\(\$course\)/self::hero_file(\$course)/;
