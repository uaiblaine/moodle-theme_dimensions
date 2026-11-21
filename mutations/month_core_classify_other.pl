# An exported event with a component is classified as a course event instead of other.
s/(public static function classify_exported\(array \$event\): string \{.*?!== ''\) \{\n\s+return )self::OTHER;/${1}self::COURSE;/s;
