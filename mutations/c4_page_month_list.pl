# Hand a list for the month to optional_param(), which throws, in the reader of the page.
s/\$month = self::scalar_param\(state::PARAM_MONTH\);\n        if \(\n/\$month = (string) optional_param(state::PARAM_MONTH, '', PARAM_RAW);\n        if (\n/;
