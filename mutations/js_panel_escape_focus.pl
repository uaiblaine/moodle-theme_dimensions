# Close the panel on Escape without giving the focus back to its poster. The panel collapses under
# the reader's focus and the focus falls to the document's body: a keyboard reader is dropped at
# the top of the page with no way back to the area they were in. The sweep exercises
# categorypanel_source_test's assertion, not a browser.
s/(poster\.contains\(active\)\)\) \{\n\s+event\.preventDefault\(\);\n\s+)closeArea\(true, true\);/${1}closeArea(false, true);/;
