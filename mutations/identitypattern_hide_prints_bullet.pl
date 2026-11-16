# Print the character a hiding slot consumes: nothing is hidden.
s/\$masked \.= self::HIDDEN;/\$masked .= \$characters[\$next - 1];/;
