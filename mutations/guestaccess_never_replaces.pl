# The entry never replaces a session somebody is logged into.
s/        if \(isloggedin\(\)\) \{\n            return false;\n        \}\n//;
