# Read each step's image on its own: one {files} statement per step instead of one for the answer.
s/\$step\['image'\] = self::image\(\$images\[\$step\['courseid'\]\] \?\? null\);/\$step['image'] = self::image(\$images ? cards::images([\$step['courseid'] => \$refrows[\$step['courseid']]], false, true)[\$step['courseid']] : null);/;
