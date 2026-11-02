# Admit every category: neither the range nor the feed withholds an unlisted one.
s/        return category_access::filter_categories\(\$categories\);/        return \$categories;/;
