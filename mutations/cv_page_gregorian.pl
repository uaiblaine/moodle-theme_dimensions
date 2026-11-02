# Serve a visitor on another calendar type.
s/        try \{\n            window::require_gregorian\(\);\n        \} catch \(refusal \$refusal\) \{\n            return self::to_login\(\);/        try {\n            \$unused = 0;\n        } catch (refusal \$refusal) {\n            return self::to_login();/;
