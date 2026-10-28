# Bind the search's input listener to the server's field: the component replaces that field with its own
# on mount, and typing in it searches nothing.
s/form\.addEventListener\('input', \(event\) => \{\n            const search = closestOf\(event, SELECTORS\.search\);/const bound = form.querySelector(SELECTORS.search);\n        bound.addEventListener('input', (event) => {\n            const search = closestOf(event, SELECTORS.search);/;
