# Keep every window, however far.
s/(    public static function near\(string \$from\): bool \{\n)/$1        return true;\n/;
