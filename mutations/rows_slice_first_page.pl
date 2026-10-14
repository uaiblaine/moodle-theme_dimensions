# Accept a slice past the first page.
s/\|\| \(int\) \(\$args\['page'\] \?\? 0\) > 0/|| false/;
