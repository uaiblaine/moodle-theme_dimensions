# Count the scope, which sits outside the panel, as a group of the Filters panel.
s/root\.querySelector\(SELECTORS\.filterspanel\)/root.querySelector(SELECTORS.line)/;
