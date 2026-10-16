# Keep the favourites on the request where nothing honours them (a visitor's page, the SQL cut): the
# unnarrowed page then claims a filter, and "Clear filters" clears nothing visible.
s/\n            \$criteria->favourites = false;//;
