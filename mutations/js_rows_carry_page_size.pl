# Stop sending the address's page size with a rows request, so every later page is cut at the site
# default whatever the listing on screen was opened at. The sweep exercises listing_account_source_test's
# assertion, not a browser.
s/        perpage: state\.pagesize,\n//;
