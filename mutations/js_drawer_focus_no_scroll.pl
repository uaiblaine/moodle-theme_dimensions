# Open a sub-panel with a scrolling focus: the browser scrolls the drawer's viewport to the back control while the panel
# is still sliding in, and the panel is drawn off to the left of it.
s/(panel\.querySelector\(SELECTORS\.BACK\)\?\.focus\()\{preventScroll: true\}\)/${1})/;
