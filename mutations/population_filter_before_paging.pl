# Cut the page before the favourites and the state chip filter it: the filters run on a population of one page.
s/(            if \(\$criteria->state !== null\) \{\n                \$ids = array_values\(array_filter\(\$ids, static function \(int \$courseid\) use \(\$verdicts, \$criteria\): bool \{)/            \$ids = array_slice(\$ids, 0, \$result->pagesize);\n$1/;
