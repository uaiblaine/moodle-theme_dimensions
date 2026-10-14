# Give "Clear filters" Bootstrap's small link button back, whose padding made it a 29.5px box beside
# a line of text; the design draws it as a text button. The sweep exercises
# category_listing_markup_test's assertion.
s/<a class="dim-toolbar__clear" /<a class="btn btn-link btn-sm dim-toolbar__clear" /;
