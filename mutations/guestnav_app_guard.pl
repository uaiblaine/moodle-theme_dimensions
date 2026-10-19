# Drop the app clause: the Moodle app's web views get the visitor's actions.
s/\n[^\n]*if \(\\core_useragent::is_moodle_app\(\)\) \{\n[^\n]*return false;\n[^\n]*\}//;
