# Freeze the badge on the Filter summary at the number the page was LOADED with: the payload
# carries a constant instead of this request's own count, so a term typed in the search box or a
# period picked in the calendar moves the rows, the count line and the clear link while the one
# number a reader with the filter panel collapsed can still see goes on reporting something else.
# The sentence a screen reader is read is left alone by this mutation on purpose - a badge whose
# two halves disagree is the shape this defect takes when only the visible half is repainted.
s/'filtercount' => \$toolbar->filtercount,/'filtercount' => 0,/;
