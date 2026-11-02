# Hand a list for the month to optional_param(), which throws, in the script's forward.
s/\$month = self::scalar_param\(state::PARAM_MONTH\);\n        if \(\$month !== ''\)/\$month = optional_param(state::PARAM_MONTH, '', PARAM_RAW);\n        if (\$month !== '')/;
