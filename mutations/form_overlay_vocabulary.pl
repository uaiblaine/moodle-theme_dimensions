# Validate the submitted overlay with a plain cast, the way it was read before 0 joined the
# vocabulary: the empty string a select hands back for an unknown choice then reads as
# "Transparent" and the form accepts it.
s/branding::overlay\(\$data\['overlay'\] \?\? null\) === null/!in_array((int) (\$data['overlay'] ?? 0), branding::OVERLAYS, true)/;
