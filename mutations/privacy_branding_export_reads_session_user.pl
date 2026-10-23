# The export reads the session user instead of the request's subject.
s/(public static function export_user_data\(approved_contextlist \$contextlist\): void \{\n\s+global \$DB)(;\n\n\s+\$userid = \(int\) )\$contextlist->get_user\(\)->id;/$1, \$USER$2\$USER->id;/;
