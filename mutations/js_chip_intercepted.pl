# Leave a pressed chip to its link: with the client running, the page reloads instead of fetching.
s/const chip = Listing\.closestOf\(event, SELECTORS\.chipremove\);/const chip = null;/;
