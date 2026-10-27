# Leave the posters that open a grouped page (and the root) lazy: the first row of the page waits for
# layout before its covers are even requested.
s/\$slice\[.directory.\]\[\$index\]\[.eager.\] = \$this->firstpaint && !\$entry\[.hidden.\] && \$eager\+\+ < self::FIRST_ROW;/\$slice["directory"][\$index]["eager"] = false;/;
