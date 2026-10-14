# Never mark a favourite: the star is unset on every card and the favourites chip is empty,
# whichever page the favourite course sits on.
s/'favourite' => isset\(\$favourites\[\$courseid\]\),/'favourite' => false,/;
