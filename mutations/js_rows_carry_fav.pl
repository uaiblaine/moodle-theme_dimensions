# Leave the favourites out of the rows request: the toggle repaints as pressed over an unnarrowed page.
s/(chip: state\.chip,\n)        fav: state\.fav,\n(        perpage: state\.pagesize,)/$1$2/;
