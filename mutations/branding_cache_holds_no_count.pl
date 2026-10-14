# Cache a number beside the branding: the one payload shared by every viewer then carries a count.
s/(\n[ \t]*'image' => \(\$file !== null && \$file->is_valid_image\(\)\) \? \[)/\n            'total' => 0,$1/;
