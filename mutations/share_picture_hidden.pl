# Show the share picture on the course's own pages: it exists for the shared link alone.
s/(landing page, the pair of the public description above\. \*\/\n\s*'visibility' => course_handler::)NOTVISIBLE/${1}VISIBLETOALL/;
