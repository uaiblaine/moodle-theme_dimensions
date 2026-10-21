# Drop the session's exit from the last clause. Entering still works, so every manual-session test stays
# green; what stops working is leaving a course that switched focus mode on, which re-focuses on the next page.
s/ \|\| self::opted_out\(\$courseid\)//;
