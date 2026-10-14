# Read the course contacts for a visitor's cards too, which an anonymous card never prints.
s/\$contacts = \$result->anonymous \? \[\] : cards::contacts\(\$rows\);/\$contacts = cards::contacts(\$rows);/;
