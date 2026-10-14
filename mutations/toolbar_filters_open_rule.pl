# Open the filter panel on the server for a search alone: the search row's term is
# already visible, and the design draws the panel closed for it.
s/'filtersopen' => \$this->criteria->state !== null\n/'filtersopen' => \$this->criteria->has_filters() || \$this->criteria->state !== null\n/;
