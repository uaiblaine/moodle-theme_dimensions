# Leave Bootstrap's toggle on the user button: its data API opens the dropdown beside the layer on the same press.
s/toggle\.removeAttribute\('data-bs-toggle'\);/void toggle;/;
