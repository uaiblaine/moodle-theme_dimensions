# Drop the state chip on the way into the criteria: the page still draws the cards of the
# filtered request but every number it prints - the total, the counts, whether there is a next
# page - is the unfiltered one, which is the defect a post-filter count exists to prevent.
s/'state' => \$chip === '' \? null : \$chip,/'state' => null,/;
