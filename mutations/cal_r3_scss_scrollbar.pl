# Show the native scrollbar under the days, beside the map that replaces it.
s/(scroll-snap-type: x proximity;\n\s*)scrollbar-width: none;/$1scrollbar-width: auto;/;
