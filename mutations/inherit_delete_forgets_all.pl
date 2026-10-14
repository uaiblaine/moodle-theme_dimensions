# Forget nothing when a row is deleted: a descendant keeps drawing a pair nobody has any more.
s/(delete_records\(self::TABLE, \['categoryid' => \$categoryid\]\);)\n        self::forget_all\(\);/$1/;
