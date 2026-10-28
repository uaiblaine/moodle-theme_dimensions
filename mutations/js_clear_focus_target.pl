# Focus the field itself after Clear all: on a folded line it cannot take the focus, which falls to the body.
s/const focusTarget = \(\) => Listing\.focusTarget\(root\);/const focusTarget = () => root.querySelector(SELECTORS.search);/;
