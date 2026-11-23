# A category changed renews the events' half alone: the courses' half keeps its old name and pair.
s/^        self::bump\(\);\n(    \}\n\})/        self::bump(self::EVENTS);\n$1/m;
