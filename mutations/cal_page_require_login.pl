# Skip core's login gate for a real account (site policy, forced password change).
s/        require_login\(null, false\);\n        unset\(/        unset(/;
