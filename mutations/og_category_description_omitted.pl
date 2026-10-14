# Pass the public description through untrimmed, so a field holding nothing but whitespace counts
# as a description and ships an og:description of one space instead of none - which is the line a
# scraper then shows under the shared link.
s/\$description = trim\(\(string\) \(\$branding\['publicsummary'\] \?\? ''\)\);/\$description = (string) (\$branding['publicsummary'] ?? '');/;
