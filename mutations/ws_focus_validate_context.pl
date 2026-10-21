# Drop the context validation, and with it require_login() on the activity: any account can write the choice
# for a course whose activity it may not see.
s/\n[ \t]*self::validate_context\(\$context\);(?=\n)//;
