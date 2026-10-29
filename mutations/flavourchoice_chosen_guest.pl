# chosen_id() answers the guest's in-memory preference: the guest would be offered a look.
s/if \(!isloggedin\(\) \|\| isguestuser\(\)\) \{\n(\s*)return self::SITE_DEFAULT;/if (false) {\n$1return self::SITE_DEFAULT;/;
