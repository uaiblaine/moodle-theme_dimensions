# Drop the hidden field that carries the toggle: without JavaScript, Enter in the search box or the
# view toggle switches the favourites off.
s/\n    \{\{#favourites\}\}\n    <input type="hidden" name="\{\{name\}\}" value="1" data-region="dim-fav-field"\{\{\^pressed\}\} disabled\{\{\/pressed\}\}>\n    \{\{\/favourites\}\}//;
