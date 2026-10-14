# Fold the plugin's type as it comes, skipping the translation: waitlisted is then no relationship at all.
s/self::theme_relationship\(\(string\) \$state\['type'\]\)/(string) \$state['type']/;
