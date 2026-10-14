# Stop counting: the renderer never records the attempt, so the hint never arrives.
s/            \$context->suggestreset = login::suggest_reset\(login::record_failed_attempt\(\)\);\n//;
