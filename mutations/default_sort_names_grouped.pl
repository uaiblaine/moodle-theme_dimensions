# Compare the organisation with the THEME's default instead of the category's: "By area" leaves the
# address on a category whose default is flat, and the next address the page mints is flat again.
s/\$this->sort === '' \|\| \$this->sort === \$this->defaultsort/\$this->sort === '' || \$this->sort === self::SORT_CATEGORY/;
