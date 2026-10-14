# Read the TAGLINE for og:description instead of the reviewed public summary. The
# tagline is written for a reader already on the page; borrowing it puts a line under every shared
# link that nobody reviewed for that audience, and it does so for every category that has a
# tagline and no public description - which looks like a working preview, not like a defect.
s/\$description = trim\(\(string\) \(\$branding\['publicsummary'\] \?\? ''\)\);/\$description = trim((string) (\$branding['chamada'] ?? ''));/;
