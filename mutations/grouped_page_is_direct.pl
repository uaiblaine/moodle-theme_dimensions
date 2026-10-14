# Drop the partition from BOTH page-cutting modes at once: the first group then holds the
# whole recursive slice again, so a child's course is drawn there AND inside that child's own panel,
# and the count line counts children the headers beside it count a second time. One gate over two
# statements, because the property is one - "the page is the courses of THIS category" - and a mode
# that quietly stopped honouring it is one address answering two ways with only a setting between.
s/if \(self::partitions\(\$criteria\)\) \{\n([ \t]*)\$ids = array_values\(array_filter/if (false) {\n$1\$ids = array_values(array_filter/;
s/if \(self::partitions\(\$criteria\)\) \{\n([ \t]*)\$rowwhere \.= ' AND c\.category = :pagecat';/if (false) {\n$1\$rowwhere .= ' AND c.category = :pagecat';/;
