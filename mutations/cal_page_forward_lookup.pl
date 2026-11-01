# Judge the category while forwarding the address (a lookup before the redirect).
s/        \$named = state::from_request\(\);\n/        \$named = state::from_request();\n        if (\$named !== null) {\n            self::scope_for(\$named);\n        }\n/;
