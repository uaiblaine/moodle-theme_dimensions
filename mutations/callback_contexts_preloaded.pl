# Read each course's context on its own instead of with the rows.
s/\\core\\context_helper::preload_from_record\(\$course\);/unset(\$course);/;
