# Leave the close-search button hidden while the search is open.
s/(> \.dim-line__searchcap > \.dim-line__searchclose \{\n            )display: inline-flex;/$1display: none;/;
