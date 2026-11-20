# Answer an id with no user context DO_NOT_PREVENT: the batch fails open where the callback throws.
s/\$answers\[\$id\] = core_user::VIEWPROFILE_PREVENT;\n(\s*)unset\(\$pending\[\$id\]\);\n(\s*)continue;/\$answers[\$id] = core_user::VIEWPROFILE_DO_NOT_PREVENT;\n$1unset(\$pending[\$id]);\n$2continue;/
