# Create on the day under the second click, not the one the first click pressed.
s/const at = pressed\.current \|\| \{iso: iso, hour: hour\};/const at = {iso: iso, hour: hour};/;
