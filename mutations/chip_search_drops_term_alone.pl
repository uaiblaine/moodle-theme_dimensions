# Drop nothing from the search chip's address: pressing it without JavaScript keeps the term.
s/\$this->criteria->search, \[criteria::PARAM_SEARCH\]\);/\$this->criteria->search, []);/;
