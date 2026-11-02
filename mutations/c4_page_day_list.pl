# Hand a list for the open day to optional_param(), which throws, in the reader of the page.
s/\$day = self::scalar_param\(self::PARAM_DAY\);\n        if \(!self::is_day\(\$day\)\) \{\n            return '';/\$day = (string) optional_param(self::PARAM_DAY, '', PARAM_RAW);\n        if (!self::is_day(\$day)) {\n            return '';/;
