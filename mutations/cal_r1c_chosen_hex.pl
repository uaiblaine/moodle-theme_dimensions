# Hand Sass the stored value unparsed: a value forced in config.php reaches the compiler.
s/\$hex = branding::hex\(\(string\) get_config\(self::COMPONENT, self::setting\(\$key\)\)\);/\$hex = (string) get_config(self::COMPONENT, self::setting(\$key)) ?: null;/;
