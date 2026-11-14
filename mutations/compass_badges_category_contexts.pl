# Drop the preload of each course's category context: core's can_view_course_info() then looks every category of the
# batch up alone, one {context} statement each, and the hook's reads grow with the courses block_compass asks about.
s/\\core\\context_helper::preload_from_record\(\$category\);/;/;
