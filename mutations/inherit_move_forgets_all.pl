# Forget nothing when core reports a category updated: a moved category keeps its old parent's pair.
s/(function category_updated\(\\core\\event\\course_category_updated \$event\): void \{)\n        self::forget_all\(\);/$1/;
