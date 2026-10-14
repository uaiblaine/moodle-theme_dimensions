# Tell the live listing the root whatever the page is. Every request then asks the root's question on
# a category page, and the service refuses it - or answers the root's posters into a category.
s/'init', \[\$categoryid\]\);/'init', [0]);/;
