# Open the live-page window far enough that it never closes: the "load more" button then keeps
# appending for ever and paging never goes back to the real link, so one document grows without
# bound. As above, what the sweep reddens is the PHP assertion, not a browser.
s/^const PAGE_WINDOW = 10;$/const PAGE_WINDOW = 999999;/m;
