# Draw a connector's mark past the edge of what is held.
s/return piece\.left >= 0 && piece\.left \+ piece\.width <= width \? piece : null;/return piece;/;
