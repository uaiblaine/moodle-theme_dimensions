# Leave the position where it was across a zoom: the day at the start of the view moves.
s/place\(element, zoomedLeft\(was\.left, from, zoom\), false\);/place(element, was.left, false);/;
