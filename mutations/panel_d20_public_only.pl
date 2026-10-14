# List a visitor's listed area as a person's listing rather than as a slice of the public one:
# the panel then shows courses that are not public, on the internet.
s/\$options\['anonymous'\] = true;/\$options['anonymous'] = false;/;
