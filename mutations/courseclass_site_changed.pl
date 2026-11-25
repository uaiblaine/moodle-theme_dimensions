# Leave every course's figures stale after a user or a role is deleted.
s/(    public static function site_changed\(\\core\\event\\base \$event\): void \{\n        try \{\n)            self::bump\(self::SITE\);\n/$1/;
