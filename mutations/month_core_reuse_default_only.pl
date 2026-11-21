# The month block reuses core's events whatever the state: a state narrowed to a category counts events it excludes.
s/(public static function reuses_core\(state \$state\): bool \{\n\s+\$plain = \$state->to_array\(\);\n\s+)return /${1}return true || /;
