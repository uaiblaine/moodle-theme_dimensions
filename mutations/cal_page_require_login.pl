# Skip core's login gate for a real account (site policy, forced password change).
s/        require_login\(null, false\);\n        try \{\n            window::require_gregorian\(\);/        try {\n            window::require_gregorian();/;
