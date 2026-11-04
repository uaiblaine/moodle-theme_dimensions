# Hand a folded trail's steps to the connectors as shown.
s/const links = linksOf\(trail\.steps, days, look\.zone, open, zoom\);/const links = linksOf(trail.steps, days, look.zone, true, zoom);/;
