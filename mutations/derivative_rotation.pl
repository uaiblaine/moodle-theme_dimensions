# Ignore the EXIF orientation: a phone photo then renders sideways in the preview.
s/\[\$source, \$size\] = \$file->rotate_image\(\);/[\$source, \$size] = [false, false];/;
