# Keep the memoised option lines across a reset of the field cache: a select edited and reset still reads
# its old lines.
s/        self::\$optionlines = \[\];\n//;
