# A facet's counts drop the report's base condition: the group rule and the gradable learners.
s/\$wheres = \$base !== '' \? \[\"\(\{\$base\}\)\"\] : \[\];/\$wheres = [];/;
