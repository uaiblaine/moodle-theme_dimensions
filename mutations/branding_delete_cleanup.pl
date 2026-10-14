# Ignore the category's deletion: its branding row outlives it, keyed to an id that will be reissued.
s/(public static function category_deleted\(\\core\\event\\course_category_deleted \$event\): void \{)/$1\n        return;/;
