# Take the weeks' and months' least for a bar's name by days too, so a one-day bar loses its name.
s/const least = zoom === 'day' \? TRACK\.day : NAMED_BAR;/const least = NAMED_BAR;/;
