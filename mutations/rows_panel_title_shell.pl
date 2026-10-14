# Spell a panel's title address on the logged-in shell.
s/links::showcase\(\$categoryid, \[\], \$criteria->anonymous\)->out\(false\),/links::showcase(\$categoryid, [], false)->out(false),/;
