# Listen for the search on the server's field alone: the component replaces it, and typing never searches.
s/const search = Listing\.closestOf\(event, SELECTORS\.search\);/const search = form.querySelector(SELECTORS.search) === event.target ? event.target : null;/;
