# Let the automatic opening take the focus. The reader is typing in the search box when the answer
# arrives, so the panel that opens by itself would take their next keystroke and scroll the page
# from under them. The sweep exercises categorypanel_source_test's assertion, not a browser.
s/openArea\(id, \{focus: false, scroll: false, auto: true\}\);/openArea(id, {focus: true, scroll: false, auto: true});/;
