# Read the root's population RAW instead of gated: the total the hero prints, and the count of
# courses hidden from students beside it, then span the courses core hides, the ones the plugin
# withholds and the ones inside a branch this viewer may not browse. Nothing on the page says so -
# a number that is wrong looks exactly like a number that is right, which is why it is a gate. (The
# cards themselves carry no number; this line is what is left to break.)
s/\$kept = self::gate\(\$scope, self::records\(\$scope, \$plain\)\);/\$kept = self::records(\$scope, \$plain);/;
