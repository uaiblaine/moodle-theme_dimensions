# Always emit the image tags: a course without an image then ships an empty og:image.
s/'hasimage' => \$this->image !== null,/'hasimage' => true,/;
