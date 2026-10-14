# Print the page's own total on the count line at rest: a category whose courses sit in its areas
# reads "0 courses" above posters holding them all.
s/\$number = \(int\) \(\$result->counts\['total'\] \?\? \$result->total\);/\$number = \$result->total;/;
