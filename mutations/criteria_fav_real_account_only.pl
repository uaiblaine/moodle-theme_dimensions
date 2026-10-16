# Read fav off the address for whoever asks: the guest account and the anonymous shell then narrow
# to favourites they cannot have, an empty page with a filter no control on it shows.
s/'favourites' => \$favourites && self::can_favourite\(\$anonymous\),/'favourites' => \$favourites,/;
