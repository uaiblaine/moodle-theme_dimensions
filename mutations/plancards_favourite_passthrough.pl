# Pass every card's favourite flag as a boolean, so another person's list says which of its plans are favourites.
s/'favourite' => \$offered \? !empty\(\$card\['isfavourite'\]\) : null/'favourite' => !empty(\$card['isfavourite'])/;
