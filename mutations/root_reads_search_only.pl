# Let the root read an organisation off its address as well as its search: a filter the root draws
# no control for then narrows or reorders it with nothing on the page admitting it.
s/(return new self\(0, \[\n)(\s+)('search' => optional_param)/$1$2'sort' => optional_param(self::PARAM_SORT, '', PARAM_ALPHA),\n$2$3/;
