# Send a `direct` key with a panel's request. The service has no such parameter and refuses the
# whole call, so every panel would fail to open while the page around it looks unchanged.
# The sweep exercises categorypanel_source_test, not a browser.
s/(\n(\s+))withcategories: true,/$1direct: false,$1withcategories: true,/;
