# A deletion for a person clears nothing.
s/(public static function delete_data_for_user\(approved_contextlist \$contextlist\): void \{\n\s+global \$DB;\n)/$1        return;\n/;
