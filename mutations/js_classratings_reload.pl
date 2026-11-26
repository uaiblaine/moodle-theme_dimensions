# The dialogue's close no longer reloads the report's rows.
s/\(\) => reload\(report, root, args\),/() => null,/;
