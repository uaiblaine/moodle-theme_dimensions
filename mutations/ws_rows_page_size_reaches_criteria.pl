# Drop the page size on the way into the criteria, so every request is cut at the site default
# however the address was opened. The clamp then puts a page past the end back onto the last one,
# so "page 1 of a listing cut at one row" answers with page 0 of a forty-eight-row one: the whole
# listing, appended over the rows already on screen.
s/'pagesize' => \$pagesize === null \? null : \(int\) \$pagesize,/'pagesize' => null,/;
