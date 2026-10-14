# Make the switch dead: the custom login page shows whatever the setting says, so a site
# can never hand the login page back to Boost Union.
s/(public static function page_enabled\(\): bool \{\n        \$config = get_config\('theme_dimensions', 'loginpage'\);\n        if \(\$config === false \|\| \$config === ''\) \{\n            return true;\n        \}\n        )return \(\(int\) \$config\) === self::SELECT_YES;/$1return true;/;
