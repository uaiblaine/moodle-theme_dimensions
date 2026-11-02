# Hand a list for the open day to optional_param(), which throws, in the script's forward.
s/\$day = self::scalar_param\(self::PARAM_DAY\);\n        if \(self::is_day\(\$day\)\)/\$day = (string) optional_param(self::PARAM_DAY, '', PARAM_RAW);\n        if (self::is_day(\$day))/;
