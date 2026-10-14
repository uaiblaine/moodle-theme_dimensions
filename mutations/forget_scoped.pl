# Forget every course's codes when one course is deleted: the identifier leaves the statement and its parameter together.
s/        \$params\['identifier'\] = \(string\) \$courseid;\n//;
s/"component = :component AND identifier = :identifier AND linktype \$typesql"/"component = :component AND linktype \$typesql"/;
