# Focus the field itself after Clear all: on a folded line it cannot take the focus, which falls to the body.
s/const search = Line\.focusTarget\(root\.querySelector\(SELECTORS\.search\)\);/const search = root.querySelector(SELECTORS.search);/;
