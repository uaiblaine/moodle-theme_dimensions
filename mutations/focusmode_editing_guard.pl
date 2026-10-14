# Drop the editing clause. Focus mode then survives a teacher turning editing on - and the
# edit switch that would turn editing off again lives in the navbar the bar replaced, so the
# teacher is left inside a mode with no way out.
s/\n[ \t]*if \(\$page->user_is_editing\(\)\) \{\n[ \t]*return 'editing';\n[ \t]*\}\n/\n/;
