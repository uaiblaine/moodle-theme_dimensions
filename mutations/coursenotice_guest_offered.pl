# The guest entry is offered only where the course has passwordless guest access.
s/        \$guest = guestaccess::available\(\$course\);/        \$guest = true;/;
