s/(    public static function details\(array \$courseids\): array \{\n        global \$CFG, \$DB, \$USER;\n)/$1        \$GLOBALS['SESSION']->theme_dimensions_mutant = 1;\n/;
