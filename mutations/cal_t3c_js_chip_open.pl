# Draw the "no end date" chip after a bar that has an end too.
s/bar\.open && bar\.to \+ 1 < count \?/bar.to + 1 < count ?/;
