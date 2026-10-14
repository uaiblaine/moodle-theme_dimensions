# Draw no crest on a directory card whatever the child category carries: the root of the course
# index then shows the bare bands, with the hero of each category still showing its own.
s/\$badges = branding::badges_for_output\(\$childid, \$branding\['badges'\]\);/\$badges = [];/;
