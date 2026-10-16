# Leave a pressed chip to its link: with the module running, the page reloads instead of fetching.
s/const chip = closestOf\(event, SELECTORS\.chipremove\);/const chip = null;/;
