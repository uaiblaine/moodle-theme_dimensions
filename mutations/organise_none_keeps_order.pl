# "No grouping" forgets the order the reader had: every flat list comes back the newest first.
s/            return \[\$order, ''\];/            return [self::SORT_RECENT, ''];/;
