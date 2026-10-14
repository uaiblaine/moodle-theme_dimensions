# Read every ancestor row's stored colours, owncolours or not: a row saved for its tagline alone,
# holding the default pair nothing should read, then hides its ancestor's pair from everything below.
s/ AND b\.owncolours = 1//;
