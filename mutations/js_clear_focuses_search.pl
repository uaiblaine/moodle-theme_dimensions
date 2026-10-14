# Leave the focus where "Clear filters" was pressed. That control hides itself once the answer is
# in, so the focus falls to the top of the document instead of the search box.
s/search\.focus\(\{preventScroll: true\}\);/search.blur();/;
