# Focus the search field after a clear even where the line folded it away: the focus falls to the top of
# the document.
s/const search = Listing\.focusTarget\(root\);/const search = root.querySelector(SELECTORS.search);/;
