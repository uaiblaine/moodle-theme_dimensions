# Stop unobserving a course whose details batch has settled. The watch list then grows with every
# row ever appended into the document, and a batch that FAILED is asked again on every scroll of a
# server that has already refused it. No gate here runs JavaScript, so the sweep exercises
# the PHP assertion in tests/local/category/amd_source_test.php - not a browser.
s/^\s*observer\.unobserve\(card\);\n//m;
