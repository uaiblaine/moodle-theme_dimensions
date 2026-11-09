# Never read the bands: a card or row without its image loses its category's colour.
s/            : cards::bands\(\$rows\);/            : [];/;
