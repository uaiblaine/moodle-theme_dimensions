# Write the open panel with pushState instead of replaceState. Every poster opened is then a
# history entry, so the back button walks through panels instead of leaving the page, and a
# reader who opened five areas needs six presses to get back to where they came from.
s/window\.history\.replaceState\(/window.history.pushState(/;
