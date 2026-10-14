# Resolve each missing card's colours with a statement of its own, in place of the one lookup for the
# whole grid: a grid pays one lookup per card.
s/\$colours = self::colours\(\$missing\);/\$colours = [];/;
s/self::build\(\$categoryid, \$colours\[\$categoryid\]\)/self::build(\$categoryid, self::colours([\$categoryid])[\$categoryid])/;
