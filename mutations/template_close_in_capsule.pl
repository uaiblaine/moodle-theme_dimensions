# Close the search's capsule before the close-search button.
s/(\{\{\/hasmanageactions\}\}\n)(            <button type="button" class="dim-line__iconbtn dim-line__searchclose")/$1        <\/div>\n$2/;
