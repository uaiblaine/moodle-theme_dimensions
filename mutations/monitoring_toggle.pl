# Make the monitoring switch dead: the notice shows whatever the admin chose.
s/(function monitoring_notice_shown\(\): bool \{\n        \$config = get_config\('theme_dimensions', 'loginpanelshowmonitoring'\);\n        if \(\$config === false \|\| \$config === ''\) \{\n            return true;\n        \}\n        )return \(\(int\) \$config\) === self::SELECT_YES;/$1return true;/;
