# Stop recomputing whether a poster opens after a filter applied in place: each poster keeps the
# opening state the page was loaded with. A page loaded under a criterion then keeps every empty area
# a button once the filters are cleared - opening an empty panel where the area's own page and "No
# courses yet" belong (the blueprint's 2.6). The hook is still called, so the build still changes.
s/expandable\(poster, Boolean\(entry\.expandable\)\);/expandable(poster, poster.dataset.expandable === '1');/;
