# Create on a double-click of the day button and of an item too.
s/target\.closest\('a, button, \[data-cal-item\]'\) === null/target.closest('[data-cal-never]') === null/;
