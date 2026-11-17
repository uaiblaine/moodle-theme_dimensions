# Count the courses in progress again beside the card: a statement per course the drawer no longer shows (D77).
s/('resume' => self::resume\(\),\n)/$1            'inprogress' => 0,\n/;
