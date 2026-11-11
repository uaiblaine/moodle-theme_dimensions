# Fall back to a file PICKER where tool_imagepicker is absent: every image row of the category editor and
# the course form's fallback would hold a file nobody can remove (see cover_fields_are_filemanagers).
s/public const FILEMANAGER = 'filemanager';/public const FILEMANAGER = 'filepicker';/;
