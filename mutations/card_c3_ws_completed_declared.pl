# Drop completed from the details service's returns: the allowlist strips it from every answer.
s/\n\s*'completed' => new external_value\(\n\s*PARAM_BOOL,\n[^\n]*\n\s*\),//;
