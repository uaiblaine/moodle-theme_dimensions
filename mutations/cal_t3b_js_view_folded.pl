# Hand a folded trail's steps to the connectors as shown.
s/const links = linksOf\(trail\.steps, days, look\.zone, open\);/const links = linksOf(trail.steps, days, look.zone, true);/;
